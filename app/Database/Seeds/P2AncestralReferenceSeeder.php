<?php
declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/** Optional reviewed constitutional reference import only; not an eligibility grant. */
final class P2AncestralReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $areas = ['Ilorin Town', 'Iponrin', 'Afon', 'Malete', 'Akanbi', 'Ejidongari', 'Oloru', 'Owode', 'Onire', 'Lanwa', 'Ipaiye'];
        $this->db->transBegin();
        try {
            foreach ($areas as $name) {
                $type = $name === 'Ilorin Town' ? 'town' : 'district';
                $existing = $this->db->table('emirate_ancestral_areas')->where(['area_name' => $name, 'area_type' => $type])->countAllResults();
                if ($existing > 0) {
                    continue;
                }
                if (!$this->db->table('emirate_ancestral_areas')->insert([
                    'area_name' => $name,
                    'area_type' => $type,
                    'source_reference' => 'Constitution (2015); database design sections 4.5 and 25.4',
                    'is_active' => 0,
                    'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
                ])) {
                    throw new RuntimeException('Ancestral reference insert failed.');
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Reference import transaction failed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
