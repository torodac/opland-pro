<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IcneaSyncProCommand extends Command
{
    protected $signature   = 'icnea:sync-pro';
    protected $description = 'Sincroniza campos icnea_* en opland_pro.vm_propiedades';

    private string $usr  = '1540';
    private string $pwd  = 'v$c$t$321$m$r$b';
    private string $lang = 'es';

    public function handle(): void
    {
        $this->info('Iniciando sincronización Icnea → vm_propiedades...');

        $lodgings = $this->fetchCatalog();

        if (empty($lodgings)) {
            $this->error('No se recibieron datos de Icnea.');
            Log::error('IcneaSyncPro: respuesta vacía del Catalog');
            return;
        }

        $this->info(count($lodgings) . ' alojamientos recibidos de Icnea.');

        $updated  = 0;
        $inserted = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($lodgings as $lodging) {
            try {
                $lodgingId = $lodging['lodging_id'] ?? null;
                if (!$lodgingId) continue;

                $id = DB::table('vm_propiedades')
                    ->where('icnea_lodging_id', $lodgingId)
                    ->value('id');

                if (!$id) {
                    DB::table('vm_propiedades')->insert(array_merge([
                        'nombre'          => $lodging['lodging_name'] ?? $lodgingId,
                        'icnea_code'      => $lodging['acronym'] ?? null,
                        'hidden'     => 0,
                        'deleted'    => 0,
                        'blocked'    => 0,
                        'createdat'  => now(),
                        'updatedat'  => now(),
                    ], $this->mapFields($lodging)));
                    $inserted++;
                    $this->line("  NUEVO: [{$lodgingId}] " . ($lodging['lodging_name'] ?? ''));
                    continue;
                }

                DB::table('vm_propiedades')
                    ->where('id', $id)
                    ->update($this->mapFields($lodging));

                $updated++;
                $this->line("  OK: [{$lodgingId}] " . ($lodging['lodging_name'] ?? ''));

            } catch (\Throwable $e) {
                $errors++;
                Log::error('IcneaSyncPro error en lodging_id=' . ($lodging['lodging_id'] ?? '?') . ': ' . $e->getMessage());
                $this->error('  ERROR ' . ($lodging['acronym'] ?? '?') . ': ' . $e->getMessage());
            }
        }

        $this->info("Sincronización completada: {$updated} actualizados, {$skipped} omitidos (sin icnea_code en vm_propiedades), {$errors} errores.");
        Log::info("IcneaSyncPro: {$updated} actualizados, {$skipped} omitidos, {$errors} errores.");
    }

    private function fetchCatalog(): array
    {
        $soap = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <Catalog xmlns="http://ws.icnea.net/WSserver">
      <usr>{$this->usr}</usr>
      <pwd>{$this->pwd}</pwd>
      <lodging_name></lodging_name>
      <lang>{$this->lang}</lang>
    </Catalog>
  </soap:Body>
</soap:Envelope>
XML;

        $ch = curl_init('https://ws.icnea.net/WSserver.asmx');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soap,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "http://ws.icnea.net/WSserver/Catalog"',
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err || !$response) {
            Log::error('IcneaSyncPro CURL error: ' . $err);
            return [];
        }

        if (!preg_match('/<CatalogResult>(.*?)<\/CatalogResult>/s', $response, $m)) {
            Log::error('IcneaSyncPro: no se encontró CatalogResult en la respuesta');
            return [];
        }

        $json = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $data = json_decode($json, true);

        return $data['Catalog'] ?? [];
    }

    private function mapFields(array $l): array
    {
        $eq = $l['lodging_equipment'] ?? [];

        return [
            'icnea_capacidad'               => $l['maximum_capacity']        ?? null,
            'icnea_lodging_id'              => $l['lodging_id']              ?? null,
            'icnea_lodging_type'            => $l['lodging_type']            ?? null,
            'icnea_address'                 => $l['address']                 ?? null,
            'icnea_zip'                     => $l['zip']                     ?? null,
            'icnea_city'                    => $l['city']                    ?? null,
            'icnea_country_name'            => $l['country_name']            ?? null,
            'icnea_latitude'                => $l['latitude']                ?? null,
            'icnea_longitude'               => $l['longitude']               ?? null,
            'icnea_release'                 => $l['release']                 ?? null,
            'icnea_release_hours'           => $l['release_hours']           ?? null,
            'icnea_number_of_rooms'         => $l['number_of_rooms']         ?? null,
            'icnea_number_of_bathrooms'     => $l['number_of_bathrooms']     ?? null,
            'icnea_number_of_toilets'       => $l['number_of_toilets']       ?? null,
            'icnea_number_of_single_beds'   => $l['number_of_single_beds']   ?? null,
            'icnea_number_of_double_beds'   => $l['number_of_double_beds']   ?? null,
            'icnea_number_of_sofa_beds'     => $l['number_of_sofa_beds']     ?? null,
            'icnea_number_of_bunk_beds'     => $l['number_of_bunk_beds']     ?? null,
            'icnea_license_number'          => $l['license_number']          ?? null,
            'icnea_swimming_pool'           => $eq['swimming_pool']          ?? null,
            'icnea_private_swimming_pool'   => $eq['private_swimming_pool']  ?? null,
            'icnea_communal_swimming_pool'  => $eq['communal_swimming_pool'] ?? null,
            'icnea_public_swimming_pool'    => $eq['public_swimming_pool']   ?? null,
            'icnea_garden'                  => $eq['garden']                 ?? null,
            'icnea_updatedat'               => now(),
        ];
    }
}
