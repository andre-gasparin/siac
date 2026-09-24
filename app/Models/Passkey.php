<?php

namespace App\Models;

use Laravel\Passkeys\Passkey as BasePasskey;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $credential_id
 * @property array<string, mixed> $credential
 * @property-read User $user
 */
class Passkey extends BasePasskey {}
