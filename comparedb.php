<?php

/**
 * Script Name: COMPARE DB
 * Description:
 * Compares the structure of two databases
 * Useful when one database has been further developed, but the original should not be replaced
 * Instead, only the structure of the further development should be adopted.
 * Flag $aend=0 Only the differences are returned as an array
 * Flag $aend=1 The differences are directly applied to the first database
 * Author: Axel Neswadba
 * Company: a-nes Internet-Lösungen
 * URL: https://www.a-nes.de
 * Created: 20.11.2024
 * License: MIT License (https://opensource.org/licenses/MIT)
 *
 * This script is provided as-is without any warranty. Use it at your own risk.
 */

$db1_host='[host]';
$db1_db='[database 1]';
$db1_user='[User database 1]';
$db1_password='[Password database 1]';

$db1_host='[host]';
$db1_db='[database 2]';
$db1_user='[User database 2]';
$db1_password='[Password database 2]';


$aend = 0; // Flag for changes


function connectDatabase($host, $dbname, $user, $password)
{
	try {
		$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
		return new PDO($dsn, $user, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		]);
	} catch (PDOException $e) {
		die("Verbindung zur Datenbank fehlgeschlagen: " . $e->getMessage());
	}
}

// Retrieve table and column information
function getDatabaseSchema(PDO $db)
{
	$tables = [];
	$query = $db->query("SHOW TABLES");
	while ($row = $query->fetch(PDO::FETCH_NUM)) {
		$tableName = $row[0];
		$tables[$tableName] = [];
		$columnsQuery = $db->query("SHOW COLUMNS FROM `$tableName`");
		while ($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
			$tables[$tableName][$column['Field']] = [
				'Type' => $column['Type'],
				'Null' => $column['Null'],
				'Key' => $column['Key'],
				'Default' => $column['Default'],
				'Extra' => $column['Extra'],
			];
		}
	}
	return $tables;
}

// Show differences between two database schemas
function compareSchemas(array $schema1, array $schema2)
{
	$differences = [
		'missing_tables_in_second' => [],
		'missing_tables_in_first' => [],
		'columns_missing_in_second' => [],
		'columns_missing_in_first' => [],
		'column_differences' => []
	];

	foreach ($schema1 as $table => $columns) {
		if (!isset($schema2[$table])) {
			$differences['missing_tables_in_second'][] = $table;
			continue;
		}
		foreach ($columns as $columnName => $columnDetails) {
			if (!isset($schema2[$table][$columnName])) {
				$differences['columns_missing_in_second'][$table][] = $columnName;
				continue;
			}
			if ($schema2[$table][$columnName] !== $columnDetails) {
				$differences['column_differences'][$table][$columnName] = [
					'first_db' => $columnDetails,
					'second_db' => $schema2[$table][$columnName],
				];
			}
		}
	}

	foreach ($schema2 as $table => $columns) {
		if (!isset($schema1[$table])) {
			$differences['missing_tables_in_first'][] = $table;
			continue;
		}
		foreach ($columns as $columnName => $columnDetails) {
			if (!isset($schema1[$table][$columnName])) {
				$differences['columns_missing_in_first'][$table][] = $columnName;
			}
		}
	}

	return $differences;
}

// Apply changes to the first database

function applyChanges(PDO $db, array $differences, array $schema2)
{
	foreach ($differences['missing_tables_in_first'] as $table) {
		echo "Erstelle Tabelle: $table\n";
		$columns = $schema2[$table];
		$columnDefinitions = [];
		$primaryKeys = [];

		foreach ($columns as $columnName => $columnDetails) {
			$columnDefinitions[] = "`$columnName` {$columnDetails['Type']} " .
				($columnDetails['Null'] === 'NO' ? 'NOT NULL' : 'NULL') .
				(isset($columnDetails['Default']) && $columnDetails['Default'] !== null ? " DEFAULT '{$columnDetails['Default']}'" : '') .
				($columnDetails['Extra'] ? " {$columnDetails['Extra']}" : '');

			// Check if the column is part of the primary key
			if ($columnDetails['Key'] === 'PRI') {
				$primaryKeys[] = "`$columnName`";
			}
		}

		// If a primary key exists, add it
		if (!empty($primaryKeys)) {
			$columnDefinitions[] = "PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
		}

		$sql = "CREATE TABLE `$table` (" . implode(', ', $columnDefinitions) . ")";
		$db->exec($sql);
	}

	foreach ($differences['columns_missing_in_first'] as $table => $columns) {
		foreach ($columns as $column) {
			$details = $schema2[$table][$column];
			echo "Füge Spalte hinzu: $table.$column\n";
			$sql = "ALTER TABLE `$table` ADD `$column` {$details['Type']} " .
				($details['Null'] === 'NO' ? 'NOT NULL' : 'NULL') .
				(isset($details['Default']) && $details['Default'] !== null ? " DEFAULT '{$details['Default']}'" : '') .
				($details['Extra'] ? " {$details['Extra']}" : '');
			$db->exec($sql);
		}
	}

	foreach ($differences['column_differences'] as $table => $columns) {
		foreach ($columns as $column => $diff) {
			$details = $diff['second_db'];
			echo "Ändere Spalte: $table.$column\n";
			$sql = "ALTER TABLE `$table` MODIFY `$column` {$details['Type']} " .
				($details['Null'] === 'NO' ? 'NOT NULL' : 'NULL') .
				(isset($details['Default']) && $details['Default'] !== null ? " DEFAULT '{$details['Default']}'" : '') .
				($details['Extra'] ? " {$details['Extra']}" : '');
			$db->exec($sql);
		}
	}
}

// Establish a connection to both databases
$db1 = connectDatabase($db1_host, $db1_db, $db1_user, $db1_password); // Source database
$db2 = connectDatabase($db2_host, $db2_db, $db2_user, $db2_password); // Advanced staging database

$schema1 = getDatabaseSchema($db1);
$schema2 = getDatabaseSchema($db2);

$differences = compareSchemas($schema1, $schema2);

if ($aend) {
	echo "Änderungen werden angewendet...\n";
	applyChanges($db1, $differences, $schema2);
} else {
	echo "Unterschiede:\n";
	print_r($differences);
}
