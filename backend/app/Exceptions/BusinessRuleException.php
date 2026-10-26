<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de negocio impide la operación aunque los datos de entrada sean válidos
 * (charla llena, reserva duplicada, charla empezada…). Se responde con 409 y un código
 * estable del contrato. Distinto de una validación (422) o de un permiso (403).
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
