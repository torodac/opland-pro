<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrationFromMysqlSeeder extends Seeder
{
    // opland_responsable.id → users.id
    private array $responsableMap = [1 => 1, 2 => 11, 3 => 18, 4 => 37];

    public function run(): void
    {
        $path = database_path('data/opland_desa.sql');
        if (!file_exists($path)) {
            $this->command->error("Fichero no encontrado: {$path}");
            return;
        }

        $sql = file_get_contents($path);
        $this->command->info('SQL cargado — iniciando migración...');






        // seedUsers omitido — usuarios ya existen en producción
        $this->seedEstados($sql);
        $this->seedPrioridades($sql);
        $this->seedTipoCaja($sql);
        $this->seedTipoTarea($sql);
        $this->seedEtiquetas($sql);
        $this->seedConceptos($sql);
        $this->seedClientes($sql);
        $this->seedContactos($sql);
        $this->seedProyectos($sql);
        $this->seedProyectoComentarios($sql);
        $this->seedBonos($sql);
        $this->seedPresupuestos($sql);
        $this->seedPresupuestoLineas($sql);
        $this->seedFtaSoportadas($sql);
        $this->seedFacturas($sql);
        $this->seedFacturaLineas($sql);
        $this->seedFacturaPresupuesto($sql);
        $this->seedTareas($sql);
        $this->seedImputaciones($sql);
        $this->seedBanco($sql);
        $this->seedCaja($sql);
        $this->seedConfiguracion($sql);





        $this->resetSequences();
        $this->command->info('Migración completada.');
    }

    // ── Parser genérico ─────────────────────────────────────────────────────

    private function parseTable(string $sql, string $table): array
    {
        $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '`\s+\(([^)]+)\)\s+VALUES\s*([\s\S]*?);/';
        $results = [];

        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $columns = array_map(
                fn($c) => trim(trim($c), '`'),
                explode(',', $match[1])
            );
            $rows = $this->parseSqlValues($match[2]);
            foreach ($rows as $row) {
                if (count($row) === count($columns)) {
                    $results[] = array_combine($columns, $row);
                }
            }
        }

        return $results;
    }

    private function parseSqlValues(string $block): array
    {
        $rows  = [];
        $len   = strlen($block);
        $i     = 0;

        while ($i < $len) {
            while ($i < $len && in_array($block[$i], [' ', "\n", "\r", "\t", ','])) {
                $i++;
            }
            if ($i >= $len) break;
            if ($block[$i] !== '(') { $i++; continue; }
            $i++; // skip '('

            $row     = [];
            $current = '';
            $inStr   = false;
            $quote   = null;

            while ($i < $len) {
                $c = $block[$i];

                if ($inStr) {
                    if ($c === '\\' && $i + 1 < $len) {
                        $next     = $block[$i + 1];
                        $current .= match ($next) {
                            'n'  => "\n",
                            'r'  => "\r",
                            't'  => "\t",
                            "'"  => "'",
                            '"'  => '"',
                            '\\' => '\\',
                            default => $next,
                        };
                        $i += 2;
                        continue;
                    }
                    if ($c === $quote) {
                        $inStr = false;
                        $i++;
                        continue;
                    }
                    $current .= $c;
                    $i++;
                    continue;
                }

                if ($c === "'" || $c === '"') {
                    $inStr = true;
                    $quote = $c;
                    $i++;
                    continue;
                }

                if ($c === ',') {
                    $row[]   = $current === 'NULL' ? null : $current;
                    $current = '';
                    $i++;
                    continue;
                }

                if ($c === ')') {
                    $row[] = $current === 'NULL' ? null : $current;
                    $rows[] = $row;
                    $i++;
                    break;
                }

                $current .= $c;
                $i++;
            }
        }

        return $rows;
    }


    private function n($v): ?int
    {
        if ($v === null || trim((string)$v) === '' || trim((string)$v) === 'NULL') return null;
        $i = (int) $v;
        return $i > 0 ? $i : null;
    }


    private function dec($v): ?float
    {
        if ($v === null || trim((string)$v) === '' || trim((string)$v) === 'NULL') return null;
        return (float) $v;
    }


    private function dat($v): ?string
    {
        if ($v === null || trim((string)$v) === '' || trim((string)$v) === 'NULL' || trim((string)$v) === '0000-00-00') return null;
        return trim((string)$v);
    }

    private function bool(mixed $v): bool
    {
        return $v === '1' || $v === 1 || $v === true;
    }

    private function mapResponsable(mixed $id): ?int
    {
        $id = (int) $id;
        return $this->responsableMap[$id] ?? null;
    }

    // ── Seeders por tabla ────────────────────────────────────────────────────

    private function seedUsers(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_usuarios');
        foreach ($rows as $r) {
            $raw = $r['password'];
            $pass = ($raw && $raw !== '***')
                ? Hash::make($raw)
                : Hash::make('changeme2024');

            DB::table('admin_users')->insert([
                'id'         => $r['id'],
                'rol'        => (int) ($r['id_rol'] ?? 0),
                'name'       => $r['nombre'],
                'email'      => $r['email'],
                'password'   => $pass,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('users: ' . count($rows));
    }

    private function seedEstados(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_estado');
        foreach ($rows as $r) {
            DB::table('estados')->insert(['id' => $r['id'], 'nombre' => $r['nombre']]);
        }
        $this->command->info('estados: ' . count($rows));
    }

    private function seedPrioridades(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_prioridad');
        foreach ($rows as $r) {
            DB::table('prioridades')->insert(['id' => $r['id'], 'nombre' => $r['nombre']]);
        }
        $this->command->info('prioridades: ' . count($rows));
    }

    private function seedTipoCaja(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_tipo_caja');
        foreach ($rows as $r) {
            DB::table('tipo_caja')->insert(['id' => $r['id'], 'nombre' => $r['nombre']]);
        }
        $this->command->info('tipo_caja: ' . count($rows));
    }

    private function seedTipoTarea(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_tipo_tarea');
        foreach ($rows as $r) {
            DB::table('tipo_tarea')->insert([
                'id'         => $r['id'],
                'nombre'     => $r['nombre'],
                'created_at' => $this->dat($r['createdat']) ?? now(),
                'updated_at' => $this->dat($r['updatedat']) ?? now(),
            ]);
        }
        $this->command->info('tipo_tarea: ' . count($rows));
    }

    private function seedEtiquetas(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_etiquetas');
        foreach ($rows as $r) {
            DB::table('etiquetas')->insert(['id' => $r['id'], 'nombre' => $r['nombre']]);
        }
        $this->command->info('etiquetas: ' . count($rows));
    }

    private function seedConceptos(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_conceptos');
        foreach ($rows as $r) {
            DB::table('conceptos')->insert(['id' => $r['id'], 'nombre' => $r['nombre']]);
        }
        $this->command->info('conceptos: ' . count($rows));
    }

    private function seedClientes(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_clientes');
        foreach ($rows as $r) {
            DB::table('clientes')->insert([
                'id'               => $r['id'],
                'nombre'           => $r['nombre'],
                'nombre_fiscal'    => $r['nombre_fiscal'],
                'nif'              => $r['nif'],
                'direccion'        => $r['direccion'],
                'cp'               => $r['cp'],
                'poblacion'        => $r['poblacion'],
                'sector'           => $r['sector'],
                'direccion_postal' => $r['direccion_postal'],
                'cp_postal'        => $r['cp_postal'],
                'poblacion_postal' => $r['poblacion_postal'],
                'deleted'          => $this->bool($r['deleted']),
            ]);
        }
        $this->command->info('clientes: ' . count($rows));
    }

    private function seedContactos(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_contactos');
        foreach ($rows as $r) {
            $this->safeInsert('contactos', [
                'id'          => $r['id'],
                'nombre'      => $r['nombre'],
                'puesto'      => $r['puesto'],
                'telefono'    => $r['telefono'],
                'mail'        => $r['mail'],
                'id_clientes' => $this->n($r['id_clientes']),
            ]);
        }
        $this->command->info('contactos: ' . count($rows));
    }

    private function seedProyectos(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_proyectos');
        foreach ($rows as $r) {
            DB::table('proyectos')->insert([
                'id'          => $r['id'],
                'nombre'      => $r['nombre'],
                'codigo'      => $r['codigo'] ?? '',
                'id_clientes' => $this->n($r['id_clientes']),
                'deleted'     => $this->bool($r['deleted']),
            ]);
        }
        $this->command->info('proyectos: ' . count($rows));
    }

    private function seedProyectoComentarios(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_proyectos_comentarios');
        foreach ($rows as $r) {
            $this->safeInsert('proyecto_comentarios', [
                'id'           => $r['id'],
                'nombre'       => $r['nombre'],
                'id_proyectos' => $this->n($r['id_proyectos']),
                'comentario'   => ($r['comentario'] !== null && trim($r['comentario']) !== 'NULL' ? trim($r['comentario']) : null),
                'file_fichero' => ($r['file_fichero'] !== null && trim($r['file_fichero']) !== 'NULL' ? trim($r['file_fichero']) : null),
                'createuser'   => $this->n($r['createuser']),
                'updateuser'   => $this->n($r['updateuser']),
                'created_at'   => $this->dat($r['createdat']) ?? now(),
                'updated_at'   => $this->dat($r['updatedat']) ?? now(),
            ]);
        }
        $this->command->info('proyecto_comentarios: ' . count($rows));
    }

    private function seedBonos(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_bonos');
        foreach ($rows as $r) {
            $this->safeInsert('bonos', [
                'id'                 => $r['id'],
                'nombre'             => $r['nombre'],
                'control_user'       => $this->n($r['control_user']),
                'horas'              => $this->dec($r['horas']),
                'fecha_contratacion' => $this->dat($r['fecha_contratacion']),
                'id_clientes'        => $this->n($r['id_clientes']),
                'deleted'            => $this->bool($r['deleted']),
            ]);
        }
        $this->command->info('bonos: ' . count($rows));
    }

    private function seedPresupuestos(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_presupuestos');
        foreach ($rows as $r) {
            $this->safeInsert('presupuestos', [
                'id'                 => $r['id'],
                'nombre'             => $r['nombre'],
                'num_ppto'           => $r['num_ppto'],
                'descripcion'        => ($r['descripcion'] !== null && trim($r['descripcion']) !== 'NULL' ? trim($r['descripcion']) : null),
                'id_clientes'        => $this->n($r['id_clientes']),
                'id_proyectos'       => $this->n($r['id_proyectos']),
                'fecha_presentacion' => $this->dat($r['fecha_presentacion']),
                'fecha_aprobacion'   => $this->dat($r['fecha_aprobacion']),
                'importe'            => $this->dec($r['importe']),
                'file_documento'     => ($r['file_documento'] !== null && trim($r['file_documento']) !== 'NULL' ? trim($r['file_documento']) : null),
                'dtototale'          => $this->dec($r['dtototale']),
                'dtototalporc'       => $this->dec($r['dtototalporc']),
                'code'               => $this->dec($r['code']),
            ]);
        }
        $this->command->info('presupuestos: ' . count($rows));
    }

    private function seedPresupuestoLineas(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_concepto_presupuesto');
        foreach ($rows as $r) {
            $this->safeInsert('presupuesto_lineas', [
                'id'               => $r['id'],
                'nombre'           => $r['nombre'],
                'id_presupuestos'  => $this->n($r['id_presupuestos']),
                'precio'           => $this->dec($r['precio']),
                'descuentoe'       => $this->dec($r['descuentoe']),
                'id_conceptos'     => $this->n($r['id_conceptos']),
                'descuentoporc'    => $this->dec($r['descuentoporc']),
                'deleted'          => $this->bool($r['deleted'] ?? 0),
            ]);
        }
        $this->command->info('presupuesto_lineas: ' . count($rows));
    }

    private function seedFtaSoportadas(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_fta_soportadas');
        foreach ($rows as $r) {
            DB::table('fta_soportadas')->insert([
                'id'              => $r['id'],
                'nombre'          => $r['nombre'],
                'fecha_emision'   => $this->dat($r['fecha_emision']),
                'nif_proveedor'   => ($r['nif_proveedor'] !== null && trim($r['nif_proveedor']) !== 'NULL' ? trim($r['nif_proveedor']) : null),
                'nombre_proveedor'=> ($r['nombre_proveedor'] !== null && trim($r['nombre_proveedor']) !== 'NULL' ? trim($r['nombre_proveedor']) : null),
                'concepto'        => ($r['concepto'] !== null && trim($r['concepto']) !== 'NULL' ? trim($r['concepto']) : null),
                'bi'              => $this->dec($r['bi']),
                'iva'             => $this->dec($r['iva']),
                'suplidos'        => $this->dec($r['suplidos']),
                'total_factura'   => $this->dec($r['total_factura']),
                'irpf'            => $this->dec($r['irpf']),
                'total_a_pagar'   => $this->dec($r['total_a_pagar']),
                'observacion'     => ($r['observacion'] !== null && trim($r['observacion']) !== 'NULL' ? trim($r['observacion']) : null),
                'file_documento'  => $r['file_documento'] ?: null,
            ]);
        }
        $this->command->info('fta_soportadas: ' . count($rows));
    }

    private function seedFacturas(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_facturas');
        foreach ($rows as $r) {
            DB::table('facturas')->insert([
                'id'              => $r['id'],
                'nombre'          => $r['nombre'],
                'num_fact'        => $r['num_fact'],
                'descripcion'     => ($r['descripcion'] !== null && trim($r['descripcion']) !== 'NULL' ? trim($r['descripcion']) : null),
                'fecha_emision'   => $this->dat($r['fecha_emision']),
                'id_clientes'     => $this->n($r['id_clientes']),
                'id_proyectos'    => $this->n($r['id_proyectos']),
                'dtototae'        => $this->dec($r['dtototae']) ?? 0,
                'dtototalporc'    => $this->dec($r['dtototalporc']),
                'iva'             => $r['IVA'] ?? $r['iva'] ?? 21,
                'base_imponible'  => $this->dec($r['base_imponible']),
                'total_a_pagar'   => $this->dec($r['total_a_pagar']),
                'incobrable'      => $this->bool($r['incobrable']),
                'file_documento'  => ($r['file_documento'] !== null && trim($r['file_documento']) !== 'NULL' ? trim($r['file_documento']) : null),
                'deleted'         => $this->bool($r['deleted']),
                'createuser'      => $this->n($r['createuser']),
                'updateuser'      => $this->n($r['updateuser']),
                'created_at'      => $this->dat($r['createdat']) ?? now(),
                'updated_at'      => $this->dat($r['updatedat']) ?? now(),
            ]);
        }
        $this->command->info('facturas: ' . count($rows));
    }

    private function seedFacturaLineas(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_concepto_factura');
        foreach ($rows as $r) {
            $this->safeInsert('factura_lineas', [
                'id'            => $r['id'],
                'nombre'        => $r['nombre'],
                'id_facturas'   => $this->n($r['id_facturas']),
                'id_conceptos'  => $this->n($r['id_conceptos']),
                'precio'        => $this->dec($r['precio']),
                'descuentoe'    => $this->dec($r['descuentoe']),
                'descuentoporc' => $this->dec($r['descuentoporc']),
                'deleted'       => $this->bool($r['deleted']),
            ]);
        }
        $this->command->info('factura_lineas: ' . count($rows));
    }

    private function seedFacturaPresupuesto(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_fact_presup');
        foreach ($rows as $r) {
            $this->safeInsert('factura_presupuesto', [
                'id'              => $r['id'],
                'nombre'          => $r['nombre'],
                'id_facturas'     => $this->n($r['id_facturas']),
                'id_presupuestos' => $this->n($r['id_presupuestos']),
            ]);
        }
        $this->command->info('factura_presupuesto: ' . count($rows));
    }

    private function seedTareas(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_tareas');
        foreach ($rows as $r) {
            $responsable = $this->mapResponsable($r['id_responsable']);
            $prioridad   = ($r['id_prioridad'] && (int)$r['id_prioridad'] > 0) ? $r['id_prioridad'] : null;
            $estado      = ($r['id_estado']    && (int)$r['id_estado']    > 0) ? $r['id_estado']    : null;

            // createdat en MySQL es date, updatedat es time — usamos now() si están vacíos
            $createdAt = $this->dat($r['createdat']) ? $this->dat($r['createdat']) . ' 00:00:00' : now();
            $updatedAt = $this->dat($r['createdat']) ? $this->dat($r['createdat']) . ' ' . ($this->dat($r['updatedat']) ?? '00:00:00') : now();

            $this->safeInsert('tareas', [
                'id'              => $r['id'],
                'nombre'          => $r['nombre'],
                'comentario'      => ($r['comentario'] !== null && trim($r['comentario']) !== 'NULL' ? trim($r['comentario']) : null),
                'id_responsable'  => $responsable,
                'id_prioridad'    => $prioridad,
                'id_estado'       => $estado,
                'id_proyectos'    => $this->n($r['id_proyectos']),
                'fecha_aproximada'=> $this->dat($r['fecha_aproximada']),
                'horas_estimadas' => $this->dec($r['horas_estimadas']) ?? 0,
                'file_captura'    => $r['file_captura'] ?: null,
                'tags_etiquetas'  => ($r['tags_etiquetas'] !== null && trim($r['tags_etiquetas']) !== 'NULL' ? trim($r['tags_etiquetas']) : null),
                'id_bonos'        => $this->n($r['id_bonos']),
                'id_facturas'     => $this->n($r['id_facturas']),
                'id_tipo_tarea'   => $this->n($r['id_tipo_tarea']),
                'deleted'         => $this->bool($r['deleted']),
                'createuser'      => $this->n($r['createuser']),
                'updateuser'      => $this->n($r['updateuser']),
                'created_at'      => $createdAt,
                'updated_at'      => $updatedAt,
            ]);
        }
        $this->command->info('tareas: ' . count($rows));
    }

    private function seedImputaciones(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_imputaciones');
        foreach ($rows as $r) {
            $this->safeInsert('imputaciones', [
                'id'                   => $r['id'],
                'nombre'               => $r['nombre'],
                'fecha_imputacion'     => $this->dat($r['fecha_imputacion']),
                'duracion'             => $r['duracion'],  // HH:MM:SS
                'no_facturable'        => $this->bool($r['no_facturable']),
                'id_tareas'            => $this->n($r['id_tareas']),
                'observacion'          => ($r['observacion'] !== null && trim($r['observacion']) !== 'NULL' ? trim($r['observacion']) : null),
                'id_presupuestos'      => $this->n($r['id_presupuestos']),
                'control_user'         => $this->n($r['control_user']),
                'factura_opland'       => ($r['factura_opland'] !== null && trim($r['factura_opland']) !== 'NULL' ? trim($r['factura_opland']) : null),
                'factura_pago_interna' => ($r['factura_pago_interna'] !== null && trim($r['factura_pago_interna']) !== 'NULL' ? trim($r['factura_pago_interna']) : null),
                'id_fta_soportadas'    => $this->n($r['id_fta_soportadas']),
                'file_imagen'          => ($r['file_imagen'] !== null && trim($r['file_imagen']) !== 'NULL' ? trim($r['file_imagen']) : null),
                'deleted'              => $this->bool($r['deleted']),
                'fecha_contable'       => $this->dat($r['fecha_contable']),
                'createuser'           => $this->n($r['createuser']),
                'updateuser'           => $this->n($r['updateuser']),
                'created_at'           => $this->dat($r['createdat']) ?? now(),
                'updated_at'           => $this->dat($r['updatedat']) ?? now(),
            ]);
        }
        $this->command->info('imputaciones: ' . count($rows));
    }

    private function seedBanco(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_banco');
        foreach ($rows as $r) {
            DB::table('banco')->insert([
                'id'            => $r['id'],
                'fecha_contable'=> $this->dat($r['fecha_contable']),
                'fecha_valor'   => $this->dat($r['fecha_valor']),
                'codigo'        => $r['codigo'],
                'nombre_banco'  => $r['nombre_banco'],
                'beneficiario'  => ($r['beneficiario'] !== null && trim($r['beneficiario']) !== 'NULL' ? trim($r['beneficiario']) : null),
                'observaciones' => ($r['observaciones'] !== null && trim($r['observaciones']) !== 'NULL' ? trim($r['observaciones']) : null),
                'importe'       => $this->dec($r['importe']),
                'saldo'         => $this->dec($r['saldo']),
                'oficina'       => $r['oficina'],
                'remesa'        => ($r['remesa'] !== null && trim($r['remesa']) !== 'NULL' ? trim($r['remesa']) : null),
                'nombre'        => $r['nombre'],
                'deleted'       => $this->bool($r['deleted']),
                'orden'         => $this->n($r['orden']),
                'createuser'    => $this->n($r['createuser']),
                'updateuser'    => $this->n($r['updateuser']),
                'created_at'    => $this->dat($r['createdat']) ?? now(),
                'updated_at'    => $this->dat($r['updatedat']) ?? now(),
            ]);
        }
        $this->command->info('banco: ' . count($rows));
    }

    private function seedCaja(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_caja');
        foreach ($rows as $r) {
            $this->safeInsert('caja', [
                'id'               => $r['id'],
                'id_tipo_caja'     => $this->n($r['id_tipo_caja']),
                'nombre'           => $r['nombre'],
                'id_clientes'      => $this->n($r['id_clientes']),
                'id_proyectos'     => $this->n($r['id_proyectos']),
                'fecha_movimiento' => $this->dat($r['fecha_movimiento']),
                'importe'          => $this->dec($r['importe']),
                'id_facturas'      => $this->n($r['id_facturas']),
                'file_documento'   => ($r['file_documento'] !== null && trim($r['file_documento']) !== 'NULL' ? trim($r['file_documento']) : null),
                'observacion'      => ($r['observacion'] !== null && trim($r['observacion']) !== 'NULL' ? trim($r['observacion']) : null),
                'deleted'          => $this->bool($r['deleted']),
                'id_fta_soportadas'=> $this->n($r['id_fta_soportadas']),
                'id_banco'         => $this->n($r['id_banco']),
                'fecha_contable'   => $this->dat($r['fecha_contable']),
                'createuser'       => $this->n($r['createuser']),
                'updateuser'       => $this->n($r['updateuser']),
                'created_at'       => $this->dat($r['createdat']) ?? now(),
                'updated_at'       => $this->dat($r['updatedat']) ?? now(),
            ]);
        }
        $this->command->info('caja: ' . count($rows));
    }

    private function seedConfiguracion(string $sql): void
    {
        $rows = $this->parseTable($sql, 'opland_configuracion');
        foreach ($rows as $r) {
            DB::table('configuracion')->insert([
                'id'         => $r['id'],
                'nombre'     => $r['nombre'],
                'valor'      => $r['valor'],
                'deleted'    => $this->bool($r['deleted']),
                'created_at' => $this->dat($r['createdat']) ?? now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('configuracion: ' . count($rows));
    }

    // ── Reset de sequences PostgreSQL ────────────────────────────────────────


    private function safeInsert(string $table, array $data): void
    {
        try {
            DB::table($table)->insert($data);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->command->warn("Skip {$table} id={$data['id']}: " . $e->getMessage());
        }
    }

    private function resetSequences(): void
    {
        $tables = [
            'admin_users', 'estados', 'prioridades', 'tipo_caja', 'tipo_tarea',
            'etiquetas', 'conceptos', 'clientes', 'contactos', 'proyectos',
            'proyecto_comentarios', 'bonos', 'presupuestos', 'presupuesto_lineas',
            'fta_soportadas', 'facturas', 'factura_lineas', 'factura_presupuesto',
            'tareas', 'imputaciones', 'banco', 'caja', 'configuracion',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1));");
            } catch (\Exception $e) {
                $this->command->warn("Sequence reset fallida en {$table}: " . $e->getMessage());
            }
        }

        $this->command->info('Sequences PostgreSQL actualizadas.');
    }
}
