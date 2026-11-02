<?php

namespace App\Support\Operations\Contracts;

interface TechnicalOperationInterface
{
    /**
     * Identificador único de la operación (slug).
     */
    public function getId(): string;

    /**
     * Título legible para la interfaz.
     */
    public function getTitle(): string;

    /**
     * Descripción detallada del impacto y alcance del proceso técnico.
     */
    public function getDescription(): string;

    /**
     * Categoría funcional (Compras, Inventario, Productos, Finanzas, Cajas).
     */
    public function getCategory(): string;

    /**
     * Nivel de riesgo ('low', 'medium', 'high', 'critical').
     */
    public function getRiskLevel(): string;

    /**
     * Icono representativo (Heroicon enum o string).
     */
    public function getIcon(): string;

    /**
     * Definición de los parámetros requeridos para el formulario.
     */
    public function getParametersSchema(): array;

    /**
     * Fase 1: Simulación / Dry-Run (Solo lectura y análisis previo).
     * Retorna datos inspeccionados, advertencias y registros que serían afectados.
     */
    public function preview(string $clientKey, string $branchCode, array $params): array;

    /**
     * Fase 2: Ejecución Atómica (en transacción SQL con rollback automático en caso de error).
     */
    public function execute(string $clientKey, string $branchCode, array $params, string $operator, ?string $ticket = null, ?string $notes = null): array;
}
