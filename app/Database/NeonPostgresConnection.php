<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection;

class NeonPostgresConnection extends PostgresConnection
{
    /**
     * This function runs right before the query is sent to the database.
     * It intercepts any Booleans (which Laravel/PDO would turn into 1/0)
     * and converts them to strings 'true'/'false' (which Postgres loves).
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
