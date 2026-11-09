<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cajón e impresora POR LOCAL (servidor en la nube).
 *
 * Hasta aquí el pulso lo daba PHP con `config/hardware.php`: una sola
 * impresora para toda la instalación, alcanzable solo si el servidor está
 * en el mismo mostrador. En la nube cada local tiene su cajón y el servidor
 * no llega a su USB: lo abre un agente instalado en el PC de caja.
 *
 *   drawer_mode  server → como antes (instalación local, config/hardware.php)
 *                agent  → agente local en 127.0.0.1:agent_port
 *                none   → el local no tiene cajón
 *   agent_token  secreto compartido con el agente: impide que cualquier
 *                página web abierta en ese PC abra el cajón.
 *
 * Columnas nuevas en inglés (ADR-001, C-2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $t) {
            if (! Schema::hasColumn('tiendas', 'drawer_mode')) {
                $t->string('drawer_mode', 10)->default('server')->after('activa');
            }
            if (! Schema::hasColumn('tiendas', 'agent_port')) {
                $t->unsignedSmallInteger('agent_port')->default(9123)->after('drawer_mode');
            }
            if (! Schema::hasColumn('tiendas', 'agent_token')) {
                $t->string('agent_token', 64)->nullable()->after('agent_port');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $t) {
            $t->dropColumn(['drawer_mode', 'agent_port', 'agent_token']);
        });
    }
};
