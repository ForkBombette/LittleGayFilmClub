<?php
declare(strict_types=1);
namespace LGFC;
use PDO;

final class MovieSchema
{
    public static function migrate(PDO $pdo): void
    {
        $columns = array_column($pdo->query('PRAGMA table_info(movies)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $pdo->beginTransaction();
        try {
            foreach ([
                'nominator_id' => 'INTEGER NULL REFERENCES users(id)',
                'nomination_pitch' => 'TEXT NULL',
                'mystery_alias' => 'TEXT NULL',
                'revealed_at' => 'TEXT NULL',
            ] as $name => $definition) {
                if (!in_array($name, $columns, true)) $pdo->exec("ALTER TABLE movies ADD COLUMN {$name} {$definition}");
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
