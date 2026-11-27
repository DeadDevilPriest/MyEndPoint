<?php
declare(strict_types=1);

namespace Gateway\Security;

use RuntimeException;

/** In der Login-Tabelle steht ein Passwort im Klartext statt eines Hashes von password_hash(). */
final class KlartextPasswort extends RuntimeException
{
}
