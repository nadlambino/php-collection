<?php

declare(strict_types=1);

namespace Inspira\Collection\Enums;

enum Type: string
{
	// Scalar types
	case STRING = 'string';
	case INTEGER = 'integer';
	case DOUBLE = 'double';
	case BOOLEAN = 'boolean';
	case NULL = 'null';

	// Compound types
	case ARRAY = 'array';
	case OBJECT = 'object';
	case CALLABLE = 'callable';

	// Special types
	case RESOURCE = 'resource';
	case MIXED = 'mixed';
	case NUMBER = 'number';
	case CALLBACK = 'callback';
}
