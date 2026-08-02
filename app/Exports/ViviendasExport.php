<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ViviendasExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private Collection $viviendas,
        private Collection $cuotasPorVivienda,
        private bool $conMovimientos
    ) {
    }

    public function headings(): array
    {
        if ($this->conMovimientos) {
            return ['Vivienda', 'Último propietario', 'Deuda acumulada', 'Cuotas ptes.', 'Cuotas demandadas', 'Ejercicio', 'Tipo', 'Fecha emisión', 'Estado', 'Importe', 'Pendiente'];
        }

        return ['Vivienda', 'Último propietario', 'Deuda acumulada', 'Cuotas ptes.', 'Cuotas demandadas'];
    }

    public function collection(): Collection
    {
        if (!$this->conMovimientos) {
            return $this->viviendas->map(fn ($v) => [
                $v->nombre, $v->ultimo_propietario, $v->deuda_acumulada, $v->cuotas_ptes, $v->cuotas_demandadas,
            ]);
        }

        $rows = collect();
        foreach ($this->viviendas as $v) {
            $cuotas = $this->cuotasPorVivienda->get($v->id) ?? collect();
            if ($cuotas->isEmpty()) {
                $rows->push([$v->nombre, $v->ultimo_propietario, $v->deuda_acumulada, $v->cuotas_ptes, $v->cuotas_demandadas, '', '', '', '', '', '']);
                continue;
            }
            foreach ($cuotas as $c) {
                $rows->push([
                    $v->nombre, $v->ultimo_propietario, $v->deuda_acumulada, $v->cuotas_ptes, $v->cuotas_demandadas,
                    $c->ejercicio, $c->tipo_cuota, $c->fecha_emision, $c->estado, $c->importe, $c->pendiente,
                ]);
            }
        }

        return $rows;
    }
}
