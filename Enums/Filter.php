<?php

declare(strict_types=1);

namespace Inspira\Collection\Enums;

/**
 * Enum Filter
 *
 * Represents different types of filters used for collection filtering.
 *
 * @package Inspira\Collection\Enums
 */
enum Filter
{
	case LIKE;
	case WHERE;
}
