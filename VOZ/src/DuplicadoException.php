<?php
declare(strict_types=1);

/**
 * Se lanza cuando el teléfono ya existe; lleva los datos del contacto existente.
 */
final class DuplicadoException extends RuntimeException
{
    public function __construct(public readonly array $contacto)
    {
        parent::__construct('El número ya está registrado.');
    }
}
