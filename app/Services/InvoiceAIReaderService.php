<?php

namespace App\Services;

use App\Exceptions\InvoiceReadingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lee la foto de una factura de proveedor con un modelo multimodal y
 * devuelve la extracción como arreglo PHP.
 *
 * Responsabilidad única: imagen → JSON validado estructuralmente.
 * NO confía en la aritmética de la IA (eso es de InventoryMathService)
 * y NO toca la base de datos.
 *
 * Proveedores: Gemini (responseSchema) y Claude (tool_use forzado). En
 * ambos la salida queda restringida a un esquema JSON, así que no hay
 * que "pescar" el JSON dentro de texto libre.
 */
class InvoiceAIReaderService
{
    private const PROMPT_PATH = 'prompts/smart_inventory_invoice.txt';
    private const TOOL_NAME   = 'registrar_factura';

    private const MIME_MAP = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',  'webp' => 'image/webp',
    ];

    /**
     * @return array<string, mixed> extracción con claves: es_factura, calidad_imagen, proveedor, items, totales_factura, observaciones, _meta
     *
     * @throws InvoiceReadingException
     */
    public function read(UploadedFile $image): array
    {
        [$mime, $base64] = $this->prepareImage($image);

        $driver  = (string) config('smart_inventory.driver');
        $started = microtime(true);

        $data = match ($driver) {
            'gemini'    => $this->callGemini($mime, $base64),
            'anthropic' => $this->callAnthropic($mime, $base64),
            default     => throw new InvoiceReadingException(InvoiceReadingException::NOT_CONFIGURED, "Proveedor de IA desconocido: {$driver}"),
        };

        $this->assertStructure($data);
        $this->assertQuality($data);

        $data['_meta'] = [
            'driver'      => $driver,
            'model'       => config("smart_inventory.providers.{$driver}.model"),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        return $data;
    }

    // ───────────────────────────── Imagen ─────────────────────────────

    /**
     * @return array{0:string, 1:string} [mime, base64]
     */
    private function prepareImage(UploadedFile $image): array
    {
        if (! $image->isValid()) {
            throw new InvoiceReadingException(InvoiceReadingException::IMAGE_INVALID, 'La imagen no se recibió completa. Inténtalo de nuevo.');
        }

        $ext  = strtolower($image->guessExtension() ?? '');
        $mime = self::MIME_MAP[$ext] ?? null;
        if ($mime === null) {
            throw new InvoiceReadingException(InvoiceReadingException::IMAGE_INVALID, 'Formato no soportado. Usa JPG, PNG o WEBP.');
        }

        // getimagesize lee la cabecera real del archivo (no la extensión) y no necesita GD.
        $size = @getimagesize($image->getRealPath());
        if ($size === false) {
            throw new InvoiceReadingException(InvoiceReadingException::IMAGE_INVALID, 'El archivo no es una imagen válida.');
        }

        [$width, $height] = $size;
        $minSide   = (int) config('smart_inventory.image.min_side');
        $minPixels = (int) config('smart_inventory.image.min_pixels');
        if (min($width, $height) < $minSide || $width * $height < $minPixels) {
            throw InvoiceReadingException::unreadable(
                "la imagen mide {$width}x{$height} px y es demasiado pequeña para leer texto. Usa la foto original, no una miniatura."
            );
        }

        return [$mime, base64_encode((string) file_get_contents($image->getRealPath()))];
    }

    // ───────────────────────────── Gemini ─────────────────────────────

    /** @return array<string, mixed> */
    private function callGemini(string $mime, string $base64): array
    {
        $cfg = $this->providerConfig('gemini');

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $this->prompt()]]],
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => $mime, 'data' => $base64]],
                    ['text' => 'Extrae esta factura según las instrucciones y el esquema.'],
                ],
            ]],
            'generationConfig' => [
                'temperature'      => 0,
                'responseMimeType' => 'application/json',
                'responseSchema'   => $this->toGeminiSchema($this->schema()),
            ],
        ];

        $response = $this->send(
            fn () => $this->http()->withHeaders(['x-goog-api-key' => $cfg['key']])
                ->post("{$cfg['endpoint']}/{$cfg['model']}:generateContent", $payload),
            'gemini',
        );

        $json = $response->json();

        if ($block = data_get($json, 'promptFeedback.blockReason')) {
            throw new InvoiceReadingException(InvoiceReadingException::PROVIDER_ERROR, "La IA bloqueó la imagen ({$block}).");
        }

        $finish = data_get($json, 'candidates.0.finishReason');
        if ($finish !== null && $finish !== 'STOP') {
            throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE,
                "La IA no terminó la lectura (finishReason={$finish}). Si la factura es muy larga, fotografíala por partes.");
        }

        $text = data_get($json, 'candidates.0.content.parts.0.text');

        return $this->decode(is_string($text) ? $text : '');
    }

    // ──────────────────────────── Anthropic ────────────────────────────

    /** @return array<string, mixed> */
    private function callAnthropic(string $mime, string $base64): array
    {
        $cfg = $this->providerConfig('anthropic');

        $payload = [
            'model'       => $cfg['model'],
            'max_tokens'  => 8192,
            'system'      => $this->prompt(),
            'tools'       => [[
                'name'         => self::TOOL_NAME,
                'description'  => 'Registra la extracción estructurada de la factura del proveedor.',
                'input_schema' => $this->schema(),
            ]],
            // Forzar la herramienta = salida JSON garantizada contra el esquema.
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
            'messages'    => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
                    ['type' => 'text', 'text' => 'Extrae esta factura según las instrucciones y el esquema.'],
                ],
            ]],
        ];

        $response = $this->send(
            fn () => $this->http()->withHeaders([
                'x-api-key'         => $cfg['key'],
                'anthropic-version' => $cfg['version'],
            ])->post($cfg['endpoint'], $payload),
            'anthropic',
        );

        $json = $response->json();

        if (($json['stop_reason'] ?? null) === 'max_tokens') {
            throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE,
                'La factura tiene demasiados renglones para una sola foto. Fotografíala por partes.');
        }

        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL_NAME) {
                return (array) $block['input'];
            }
        }

        throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE, 'La IA no devolvió la estructura esperada.');
    }

    // ───────────────────────────── HTTP ─────────────────────────────

    /**
     * Ejecuta la petición con reintentos solo ante fallas transitorias
     * (red, 429, 5xx). Un 4xx de validación no se reintenta.
     */
    private function send(callable $request, string $driver): Response
    {
        $retries = (int) config('smart_inventory.http.retries');
        $sleepMs = (int) config('smart_inventory.http.retry_ms');

        try {
            $response = retry(
                $retries + 1,
                function () use ($request) {
                    /** @var Response $r */
                    $r = $request();
                    if ($r->status() === 429 || $r->serverError()) {
                        $r->throw(); // fuerza el reintento
                    }

                    return $r;
                },
                fn (int $attempt) => $sleepMs * $attempt, // backoff lineal
                fn (\Throwable $e) => $e instanceof ConnectionException || $e instanceof RequestException,
            );
        } catch (ConnectionException $e) {
            Log::warning('smart_inventory.timeout', ['driver' => $driver, 'error' => $e->getMessage()]);
            throw new InvoiceReadingException(InvoiceReadingException::PROVIDER_TIMEOUT,
                'El servicio de IA no respondió a tiempo. Inténtalo en un minuto.', previous: $e);
        } catch (RequestException $e) {
            $this->failProvider($e->response, $driver, $e);
        }

        if ($response->failed()) {
            $this->failProvider($response, $driver);
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('smart_inventory.http.timeout'))
            ->connectTimeout(10);
    }

    private function failProvider(Response $response, string $driver, ?\Throwable $previous = null): never
    {
        // Nunca se registra la imagen ni la API key: solo estado y mensaje del proveedor.
        Log::error('smart_inventory.provider_error', [
            'driver' => $driver,
            'status' => $response->status(),
            'error'  => mb_substr((string) $response->body(), 0, 500),
        ]);

        $message = match (true) {
            $response->status() === 429                          => 'Se alcanzó el límite de uso del servicio de IA. Inténtalo en unos minutos.',
            in_array($response->status(), [401, 403], true)      => 'La credencial del servicio de IA no es válida. Contacta al administrador.',
            $response->status() === 413                          => 'La imagen es demasiado pesada para el servicio de IA.',
            default                                              => 'El servicio de IA falló al procesar la factura.',
        };

        throw new InvoiceReadingException(InvoiceReadingException::PROVIDER_ERROR, $message,
            ['status' => $response->status()], $previous);
    }

    // ─────────────────────────── Validación ───────────────────────────

    /** @return array<string, mixed> */
    private function decode(string $text): array
    {
        // Defensa ante un modelo que envuelva el JSON en ```json … ```.
        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)) ?? '');

        try {
            $data = json_decode($clean, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE,
                'La IA devolvió una respuesta que no es JSON válido.', previous: $e);
        }

        if (! is_array($data)) {
            throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE, 'La IA devolvió un JSON vacío.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assertStructure(array $data): void
    {
        foreach (['es_factura', 'calidad_imagen', 'items'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE, "Falta la clave «{$key}» en la respuesta de la IA.");
            }
        }

        if (! is_array($data['items'])) {
            throw new InvoiceReadingException(InvoiceReadingException::INVALID_RESPONSE, '«items» debe ser una lista.');
        }
    }

    /** @param array<string, mixed> $data */
    private function assertQuality(array $data): void
    {
        if ($data['es_factura'] !== true) {
            throw new InvoiceReadingException(InvoiceReadingException::NOT_AN_INVOICE,
                'La imagen no parece ser una factura de proveedor.');
        }

        $quality    = (array) $data['calidad_imagen'];
        $confidence = (float) ($quality['confianza'] ?? 0);
        $problems   = implode('; ', (array) ($quality['problemas'] ?? []));

        if (($quality['legible'] ?? false) !== true || $confidence < (float) config('smart_inventory.min_confidence')) {
            throw InvoiceReadingException::unreadable($problems !== '' ? $problems : 'confianza ' . round($confidence * 100) . '%');
        }

        if ($data['items'] === []) {
            throw new InvoiceReadingException(InvoiceReadingException::NO_ITEMS, 'No se encontraron productos en la factura.');
        }
    }

    // ───────────────────────────── Esquema ─────────────────────────────

    /** @return array{key:string, model:string, endpoint:string, version?:string} */
    private function providerConfig(string $driver): array
    {
        $cfg = (array) config("smart_inventory.providers.{$driver}");
        if (empty($cfg['key'])) {
            throw new InvoiceReadingException(InvoiceReadingException::NOT_CONFIGURED,
                "Falta la API key de {$driver} en .env.");
        }

        return $cfg;
    }

    private function prompt(): string
    {
        static $prompt = null;

        return $prompt ??= (string) file_get_contents(resource_path(self::PROMPT_PATH));
    }

    /**
     * Esquema canónico (JSON Schema). Claude lo usa tal cual; para Gemini se
     * traduce con toGeminiSchema().
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $num  = ['type' => ['number', 'null']];
        $str  = ['type' => ['string', 'null']];
        $conf = ['type' => 'number', 'minimum' => 0, 'maximum' => 1];

        $item = [
            'type'       => 'object',
            'properties' => [
                'producto'              => ['type' => 'string'],
                'codigo'                => $str,
                'presentacion_caja'     => $str,
                'cantidad_cajas'        => $num,
                'cantidad_unidades'     => $num,
                'unidades_totales'      => $num,
                'costo_total_compra'    => $num,
                'costo_caja'            => $num,
                'costo_unitario'        => $num,
                'iva_porcentaje'        => $num,
                'precio_venta_unitario' => $num,
                'precio_venta_caja'     => $num,
                'precio_venta_total'    => $num,
                'margen_ganancia'       => [
                    'type'       => 'object',
                    'properties' => [
                        'ganancia_neta_caja' => $num,
                        'ganancia_unitaria'  => $num,
                        'ganancia_total'     => $num,
                        'margen_porcentaje'  => $num,
                        'markup_porcentaje'  => $num,
                    ],
                    'required' => ['ganancia_neta_caja', 'ganancia_unitaria', 'ganancia_total', 'margen_porcentaje', 'markup_porcentaje'],
                ],
                'confianza' => $conf,
            ],
            'required' => [
                'producto', 'codigo', 'presentacion_caja', 'cantidad_cajas', 'cantidad_unidades',
                'unidades_totales', 'costo_total_compra', 'costo_caja', 'costo_unitario', 'iva_porcentaje',
                'precio_venta_unitario', 'precio_venta_caja', 'precio_venta_total', 'margen_ganancia', 'confianza',
            ],
        ];

        return [
            'type'       => 'object',
            'properties' => [
                'es_factura'     => ['type' => 'boolean'],
                'calidad_imagen' => [
                    'type'       => 'object',
                    'properties' => [
                        'legible'   => ['type' => 'boolean'],
                        'confianza' => $conf,
                        'problemas' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['legible', 'confianza', 'problemas'],
                ],
                'proveedor' => [
                    'type'       => 'object',
                    'properties' => [
                        'nombre'         => $str,
                        'nit'            => $str,
                        'numero_factura' => $str,
                        'fecha'          => $str,
                    ],
                    'required' => ['nombre', 'nit', 'numero_factura', 'fecha'],
                ],
                'items'           => ['type' => 'array', 'items' => $item],
                'totales_factura' => [
                    'type'       => 'object',
                    'properties' => ['subtotal' => $num, 'descuento' => $num, 'iva' => $num, 'total' => $num],
                    'required'   => ['subtotal', 'descuento', 'iva', 'total'],
                ],
                'observaciones' => $str,
            ],
            'required' => ['es_factura', 'calidad_imagen', 'proveedor', 'items', 'totales_factura', 'observaciones'],
        ];
    }

    /**
     * Gemini usa un subconjunto OpenAPI: sin uniones de tipo ni min/max en
     * todos los casos. ["number","null"] → {type: NUMBER, nullable: true}.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function toGeminiSchema(array $node): array
    {
        $out = [];

        if (isset($node['type'])) {
            $types = (array) $node['type'];
            $nonNull = array_values(array_filter($types, fn ($t) => $t !== 'null'));
            $out['type'] = strtoupper($nonNull[0] ?? 'string');
            if (in_array('null', $types, true)) {
                $out['nullable'] = true;
            }
        }

        if (isset($node['properties'])) {
            $out['properties'] = array_map(fn ($p) => $this->toGeminiSchema($p), $node['properties']);
            $out['propertyOrdering'] = array_keys($node['properties']);
        }
        if (isset($node['items'])) {
            $out['items'] = $this->toGeminiSchema($node['items']);
        }
        if (isset($node['required'])) {
            $out['required'] = $node['required'];
        }

        return $out;
    }
}
