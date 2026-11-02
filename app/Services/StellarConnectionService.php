<?php

namespace App\Services;

use Exception;
use PDO;
use RuntimeException;

class StellarConnectionService
{
    protected array $pool = [];

    /**
     * Obtiene una conexión PDO a la base de datos VAD10 o VAD20 de un cliente y sucursal.
     */
    public function getConnection(string $clientKey, string $branchCode = "01", string $databaseType = "vad10"): PDO
    {
        $poolKey = "{$clientKey}_{$branchCode}_{$databaseType}";
        if (isset($this->pool[$poolKey])) {
            return $this->pool[$poolKey];
        }

        $clients = config("stellar.clients", []);
        if (!isset($clients[$clientKey])) {
            throw new RuntimeException("Cliente '{$clientKey}' no configurado en stellar.php");
        }

        $clientConfig = $clients[$clientKey];
        if (!isset($clientConfig["branches"][$branchCode])) {
            throw new RuntimeException("Sucursal '{$branchCode}' no configurada para el cliente '{$clientKey}'");
        }

        $branch = $clientConfig["branches"][$branchCode];
        $server = $branch["server"] ?? "localhost";
        $dbName = strtolower($databaseType) === "vad20" ? ($branch["vad20"] ?? "VAD20") : ($branch["vad10"] ?? "VAD10");
        $trusted = !empty($branch["trusted"]);
        $uid = $branch["uid"] ?? "sa";
        $pwd = $branch["pwd"] ?? "";

        if ($trusted) {
            $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server={$server};Database={$dbName};Trusted_Connection=Yes;TrustServerCertificate=Yes;";
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server={$server};Database={$dbName};Uid={$uid};Pwd={$pwd};TrustServerCertificate=Yes;";
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $this->pool[$poolKey] = $pdo;
        return $pdo;
    }

    /**
     * Ejecuta una rutina dentro de una transacción atómica segura.
     */
    public function transaction(string $clientKey, string $branchCode, string $databaseType, callable $callback)
    {
        $pdo = $this->getConnection($clientKey, $branchCode, $databaseType);
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retorna la lista de clientes disponibles para selectores en UI.
     */
    public function getAvailableClients(): array
    {
        $clients = config("stellar.clients", []);
        $options = [];
        foreach ($clients as $key => $data) {
            $options[$key] = $data["name"] ?? $key;
        }
        return $options;
    }

    /**
     * Retorna las sucursales de un cliente específico.
     */
    public function getBranchesForClient(string $clientKey): array
    {
        $clients = config("stellar.clients", []);
        if (!isset($clients[$clientKey]["branches"])) {
            return [];
        }
        $branches = [];
        foreach ($clients[$clientKey]["branches"] as $code => $data) {
            $branches[$code] = "[$code] " . ($data["name"] ?? "Sucursal $code");
        }
        return $branches;
    }
}
