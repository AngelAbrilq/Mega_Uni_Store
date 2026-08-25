<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ConImagen;
use Spatie\Permission\Traits\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar_url', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, ConImagen, HasFactory, Notifiable, HasRoles;

    /** La foto del usuario vive en avatar_url, no en image_url. */
    public function campoImagen(): string
    {
        return 'avatar_url';
    }

    /**
     * Un usuario nuevo entra al negocio donde lo crearon.
     *
     * ── Por qué es un gancho y no una línea en el controlador ──
     *
     * Sin esto, crear un usuario desde Administración › Usuarios produce
     * una persona que no pertenece a ninguna empresa; y como el guardián de
     * contexto niega la entrada a quien no tiene negocio, esa persona
     * recibe un 403 la primera vez que intenta entrar. El administrador que
     * acaba de crearla no tendría cómo adivinar por qué.
     *
     * Puesto en el modelo cubre las tres puertas por las que hoy nace un
     * usuario —el panel, los seeders y las pruebas— y las que se agreguen
     * después.
     *
     * Es `attach` y no `sync`: quien ya pertenece a dos negocios no debe
     * perder uno porque alguien lo editó desde el otro.
     */
    protected static function booted(): void
    {
        static::created(function (self $usuario) {
            $empresaId = \App\Support\Contexto::empresaId();

            if (! $empresaId) {
                return;
            }

            if ($usuario->empresas()->whereKey($empresaId)->exists()) {
                return;
            }

            $usuario->empresas()->attach($empresaId, ['es_dueno' => false]);
        });
    }

    /**
     * Los negocios a los que pertenece esta persona.
     *
     * Es una relación de muchos a muchos y no una columna `empresa_id`
     * porque hay dueños con dos negocios. Si fuera una columna, William
     * tendría que existir dos veces —dos correos, dos claves, dos veces
     * cambiarlas— y «cambiar de negocio» sería cerrar sesión y volver a
     * entrar en vez de un botón.
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_usuario')
            ->withPivot('es_dueno')
            ->withTimestamps();
    }

    /** Los locales concretos donde tiene permiso de trabajar. */
    public function tiendas(): BelongsToMany
    {
        return $this->belongsToMany(Tienda::class, 'tienda_usuario')->withTimestamps();
    }

    /** ¿Puede entrar a este negocio? Lo pregunta el guardián de contexto. */
    public function perteneceA(int $empresaId): bool
    {
        return $this->empresas()->whereKey($empresaId)->exists();
    }

    /**
     * Los locales de ese negocio donde esta persona puede trabajar.
     *
     * Si no tiene ninguno asignado explícitamente se entiende que puede en
     * todos los del negocio. Es lo correcto para el dueño y para las
     * instalaciones de un solo local, donde asignar tiendas una por una
     * sería trabajo sin sentido.
     *
     * Vive aquí —y no repetido en el middleware, el controlador y la
     * vista— porque los tres lo necesitan y tienen que responder
     * exactamente lo mismo: si el selector ofreciera un local que el
     * guardián no acepta, el usuario escogería algo que lo saca con un 403.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Tienda>
     */
    public function tiendasEn(int $empresaId)
    {
        $suyas = $this->tiendas()
            ->where('tiendas.empresa_id', $empresaId)
            ->where('tiendas.activa', true)
            ->orderByDesc('tiendas.es_principal')
            ->orderBy('tiendas.id')
            ->get();

        if ($suyas->isNotEmpty()) {
            return $suyas;
        }

        return Tienda::where('empresa_id', $empresaId)
            ->where('activa', true)
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->get();
    }

    /**
     * Solo la gente de este negocio.
     *
     * ── Por qué es un scope y no un filtro global como en los demás ──
     *
     * `User` es la tabla por la que entra todo el mundo: el guardián de
     * sesión busca al usuario por su id en cada petición, y quien inicia
     * sesión se busca por correo antes de que exista contexto alguno. Un
     * filtro global sobre esta tabla se metería en medio de eso, y el
     * síntoma sería el peor posible: gente que no puede entrar, o que
     * queda encerrada en un bucle de ingreso.
     *
     * Así que aquí el aislamiento se pide donde se lista gente —que son
     * dos sitios, y están a la vista— y se cierra por el otro lado con
     * `resolveRouteBinding`, que es por donde alguien intentaría abrir la
     * ficha de un usuario ajeno escribiendo el id en la dirección.
     */
    public function scopeDeLaEmpresa($consulta, ?int $empresaId = null)
    {
        $empresaId ??= \App\Support\Contexto::empresaId();

        if (! $empresaId) {
            return $consulta;
        }

        return $consulta->whereHas('empresas', fn ($e) => $e->whereKey($empresaId));
    }

    /**
     * Abrir /users/5 no sirve si el 5 es de otro negocio.
     *
     * Devolver null hace que Laravel responda 404 —«no existe»— que es
     * mejor respuesta que 403: un 403 confirmaría que ese usuario sí
     * existe en algún lado.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $usuario = parent::resolveRouteBinding($value, $field);

        if (! $usuario) {
            return null;
        }

        $empresaId = \App\Support\Contexto::empresaId();

        if ($empresaId === null || \App\Support\Contexto::sinFiltroActivo()) {
            return $usuario;
        }

        return $usuario->perteneceA($empresaId) ? $usuario : null;
    }

    /** Ventas registradas por este usuario. */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Turnos de caja abiertos o cerrados por este usuario. */
    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /** El turno de caja abierto, si tiene uno. */
    public function turnoAbierto(): ?CashSession
    {
        return CashSession::abiertaDe($this->id);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
