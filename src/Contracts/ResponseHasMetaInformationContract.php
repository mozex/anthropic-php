<?php

declare(strict_types=1);

namespace Anthropic\Contracts;

use Anthropic\Responses\Meta\MetaInformation;

interface ResponseHasMetaInformationContract
{
    public function meta(): MetaInformation;
}
