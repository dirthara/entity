<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;

final class FractionalSecondsOnString
{
    #[Id]
    public int $id;

    #[Column(fractionalSeconds: 3)]
    public string $value;
}
