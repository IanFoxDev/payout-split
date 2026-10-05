<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Storage;

/**
 * Runs in a PostgreSQL or MySQL table created from schema/postgresql.sql or
 * schema/mysql.sql. The primary key (scheme, period) makes a second run of a period
 * fail, even when two processes race.
 */
final readonly class PdoRunRepository implements RunRepository
{
    public function __construct(
        private \PDO $pdo,
        private string $table = 'payout_runs',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $table) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid table name "%s".', $table));
        }
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function find(string $scheme, string $period): ?StoredRun
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT scheme, period, input_hash, rules_fingerprint, input, rules, result FROM %s WHERE scheme = ? AND period = ?',
            $this->table,
        ));
        $statement->execute([$scheme, $period]);
        /** @var array{scheme: string, period: string, input_hash: string, rules_fingerprint: string, input: string, rules: string, result: string}|false $row */
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new StoredRun($row['scheme'], $row['period'], $row['input_hash'], $row['rules_fingerprint'], $row['input'], $row['rules'], $row['result']);
    }

    public function add(StoredRun $run): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (scheme, period, input_hash, rules_fingerprint, input, rules, result) VALUES (?, ?, ?, ?, ?, ?, ?)',
            $this->table,
        ));
        try {
            $statement->execute([$run->scheme, $run->period, $run->inputHash, $run->rulesFingerprint, $run->input, $run->rules, $run->result]);
        } catch (\PDOException $e) {
            // 23505 in PostgreSQL, 23000 in MySQL: the primary key is taken.
            if (in_array($e->getCode(), ['23505', '23000'], true)) {
                throw new PeriodAlreadyStored(sprintf('Scheme "%s" already has a run for %s.', $run->scheme, $run->period), 0, $e);
            }
            throw $e;
        }
    }
}
