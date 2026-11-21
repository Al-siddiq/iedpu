<?php
declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * DB-10: exact Section 2(a) town/district labels from the 2015 Constitution.
 * Reference data only: no member eligibility, geography coverage, or branch activation.
 */
final class P2AncestralReferenceSeeder extends Seeder
{
    private const REFERENCE = [
        'Ilorin Town' => 'town',
        'Iponrin' => 'district',
        'Afon' => 'district',
        'Malete' => 'district',
        'Akanbi' => 'district',
        'Ejidongari' => 'district',
        'Oloru' => 'district',
        'Owode' => 'district',
        'Onire' => 'district',
        'Lanwa' => 'district',
        'Ipaiye' => 'district',
    ];

    public function run(): void
    {
        $this->db->transBegin();
        try {
            foreach (self::REFERENCE as $name => $type) {
                $existing = $this->db->table('emirate_ancestral_areas')
                    ->where('area_name', $name)->get()->getResultArray();
                if ($existing !== []) {
                    if (count($existing) !== 1 || $existing[0]['area_type'] !== $type) {
                        throw new RuntimeException('Constitutional reference conflict for ' . $name . '. Review manually; no overwrite.');
                    }
                    // Existing records (including administrator-approved statuses) are never changed.
                    continue;
                }
                if (!$this->db->table('emirate_ancestral_areas')->insert([
                    'area_name' => $name,
                    'area_type' => $type,
                    'source_reference' => 'IEDPU Constitution 2015, Section 2(a), PDF page 2; SHA256 c1d91b8841c1acf07dbca8da1d6cc053609a25749a9d692e581ace3d37665d29',
                    'is_active' => 0,
                    'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
                ])) {
                    throw new RuntimeException('Constitutional reference import failed.');
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Constitutional reference transaction failed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
