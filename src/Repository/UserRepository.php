<?php

declare(strict_types=1);

namespace Suisen\Repository;

use Suisen\Infra\Database;

final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** そのメールアドレスの利用者の id。いなければ登録して id を返す。 */
    public function findOrCreate(string $email): int
    {
        $this->db->pdo()
            ->prepare('INSERT IGNORE INTO users (email) VALUES (?)')
            ->execute([$email]);

        $statement = $this->db->pdo()->prepare('SELECT id FROM users WHERE email = ?');
        $statement->execute([$email]);
        return (int) $statement->fetchColumn();
    }
}
