<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On vise MySQL/MariaDB (ENUM classique)
        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            // Sur PostgreSQL, l'ENUM se gère différemment
            return;
        }

        $table = 'alerts';
        if (!Schema::hasTable($table)) return;

        // colonne possible: alert_type (ton modèle) ou type (legacy)
        $colName = null;
        if (Schema::hasColumn($table, 'alert_type')) $colName = 'alert_type';
        elseif (Schema::hasColumn($table, 'type')) $colName = 'type';

        if (!$colName) return;

        // Lire la définition colonne dans information_schema
        $dbName = DB::getDatabaseName();

        $col = DB::selectOne("
            SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ", [$dbName, $table, $colName]);

        if (!$col || empty($col->COLUMN_TYPE)) return;

        $columnType = (string) $col->COLUMN_TYPE;

        // On ne modifie que si c'est bien enum(...)
        if (stripos($columnType, 'enum(') !== 0) return;

        // Extraire le contenu entre enum(...)
        if (!preg_match('/^enum\((.*)\)$/i', $columnType, $m)) return;

        $inner = $m[1];

        // Parser la liste des valeurs ENUM en mode robuste
        // str_getcsv gère bien les virgules + quotes
        $values = str_getcsv($inner, ',', "'");

        // Normaliser
        $values = array_values(array_filter(array_map(function ($v) {
            $v = trim((string) $v);
            // MySQL renvoie parfois déjà sans quotes; ici on a déjà nettoyé
            return $v;
        }, $values), fn($v) => $v !== ''));

        // Ajouter offline et other si absents
        $toAdd = ['offline', 'other'];
        foreach ($toAdd as $v) {
            if (!in_array($v, $values, true)) $values[] = $v;
        }

        // Rebuild ENUM SQL en échappant les quotes simples
        $enumSql = "ENUM(" . implode(',', array_map(function ($v) {
            $v = str_replace("'", "\\'", $v);
            return "'" . $v . "'";
        }, $values)) . ")";

        $isNullable = ((string) $col->IS_NULLABLE) === 'YES';
        $nullSql = $isNullable ? 'NULL' : 'NOT NULL';

        $default = $col->COLUMN_DEFAULT; // peut être null
        $defaultSql = '';

        if ($default === null) {
            if ($isNullable) $defaultSql = 'DEFAULT NULL';
        } else {
            $def = (string) $default;
            // sécurité: default doit exister dans l'enum
            if (in_array($def, $values, true)) {
                $def = str_replace("'", "\\'", $def);
                $defaultSql = "DEFAULT '{$def}'";
            }
        }

        // ALTER
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$colName}` {$enumSql} {$nullSql} {$defaultSql}");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $table = 'alerts';
        if (!Schema::hasTable($table)) return;

        $colName = null;
        if (Schema::hasColumn($table, 'alert_type')) $colName = 'alert_type';
        elseif (Schema::hasColumn($table, 'type')) $colName = 'type';
        if (!$colName) return;

        $dbName = DB::getDatabaseName();

        $col = DB::selectOne("
            SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ", [$dbName, $table, $colName]);

        if (!$col || empty($col->COLUMN_TYPE)) return;

        $columnType = (string) $col->COLUMN_TYPE;
        if (stripos($columnType, 'enum(') !== 0) return;
        if (!preg_match('/^enum\((.*)\)$/i', $columnType, $m)) return;

        $values = str_getcsv($m[1], ',', "'");
        $values = array_values(array_filter(array_map('trim', $values)));

        // Retirer offline & other si présents
        $values = array_values(array_filter($values, fn($v) => !in_array($v, ['offline', 'other'], true)));

        $enumSql = "ENUM(" . implode(',', array_map(function ($v) {
            $v = str_replace("'", "\\'", $v);
            return "'" . $v . "'";
        }, $values)) . ")";

        $isNullable = ((string) $col->IS_NULLABLE) === 'YES';
        $nullSql = $isNullable ? 'NULL' : 'NOT NULL';

        $default = $col->COLUMN_DEFAULT;
        $defaultSql = '';

        if ($default === null) {
            if ($isNullable) $defaultSql = 'DEFAULT NULL';
        } else {
            $def = (string) $default;
            if (in_array($def, $values, true)) {
                $def = str_replace("'", "\\'", $def);
                $defaultSql = "DEFAULT '{$def}'";
            }
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$colName}` {$enumSql} {$nullSql} {$defaultSql}");
    }
};
