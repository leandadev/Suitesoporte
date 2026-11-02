<?php

namespace App\Support\Operations;

use App\Support\Operations\Contracts\TechnicalOperationInterface;
use App\Support\Operations\Playbooks\DeletePurchaseOperation;
use App\Support\Operations\Playbooks\ManualKardexAdjustmentOperation;
use App\Support\Operations\Playbooks\KardexAuditReconciliationOperation;
use App\Support\Operations\Playbooks\BulkProductUpdateOperation;
use App\Support\Operations\Playbooks\DeleteAnticipoOperation;
use InvalidArgumentException;

class OperationRegistry
{
    /**
     * Lista de operaciones registradas en el sistema.
     * Arquitectura 100% escalable: Para agregar un nuevo proceso técnico,
     * basta con crear la clase que implementa TechnicalOperationInterface y agregarla aquí.
     */
    protected array $operations = [
        DeletePurchaseOperation::class,
        ManualKardexAdjustmentOperation::class,
        KardexAuditReconciliationOperation::class,
        BulkProductUpdateOperation::class,
        DeleteAnticipoOperation::class,
    ];

    /**
     * Retorna todas las operaciones instanciadas.
     * @return TechnicalOperationInterface[]
     */
    public function getAll(): array
    {
        $instances = [];
        foreach ($this->operations as $opClass) {
            $instances[] = app($opClass);
        }
        return $instances;
    }

    /**
     * Obtiene una operación específica por su ID.
     */
    public function get(string $id): TechnicalOperationInterface
    {
        foreach ($this->operations as $opClass) {
            $instance = app($opClass);
            if ($instance->getId() === $id) {
                return $instance;
            }
        }
        throw new InvalidArgumentException("La operación técnica '{$id}' no está registrada en el sistema.");
    }

    /**
     * Retorna las operaciones agrupadas por categoría funcional.
     */
    public function getGroupedByCategory(): array
    {
        $grouped = [];
        foreach ($this->getAll() as $op) {
            $cat = $op->getCategory();
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $op;
        }
        return $grouped;
    }
}
