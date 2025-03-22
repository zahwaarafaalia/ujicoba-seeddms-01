<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Argument
{
    private const ALLOWED_TYPES = ['string', 'bool', 'int', 'float', 'array'];

    private ?int $mode = null;

    /**
     * Represents a console command <argument> definition.
     *
     * If unset, the `name` and `default` values will be inferred from the parameter definition.
     *
     * @param string|bool|int|float|array|null                               $default         The default value (for InputArgument::OPTIONAL mode only)
     * @param array|callable-string(CompletionInput):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function __construct(
        public string $name = '',
        public string $description = '',
        public string|bool|int|float|array|null $default = null,
        public array|string $suggestedValues = [],
    ) {
        if (\is_string($suggestedValues) && !\is_callable($suggestedValues)) {
            throw new \TypeError(\sprintf('Argument 4 passed to "%s()" must be either an array or a callable-string.', __METHOD__));
        }
    }

    /**
     * @internal
     */
    public static function tryFrom(\ReflectionParameter $parameter): ?self
    {
        /** @var self $self */
        if (null === $self = ($parameter->getAttributes(self::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null)?->newInstance()) {
            return null;
        }

        $type = $parameter->getType();
        $name = $parameter->getName();

        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The parameter "$%s" must have a named type. Untyped, Union or Intersection types are not supported for command arguments.', $name));
        }

        $parameterTypeName = $type->getName();

        if (!\in_array($parameterTypeName, self::ALLOWED_TYPES, true)) {
            throw new LogicException(\sprintf('The type "%s" of parameter "$%s" is not supported as a command argument. Only "%s" types are allowed.', $parameterTypeName, $name, implode('", "', self::ALLOWED_TYPES)));
        }

        if (!$self->name) {
            $self->name = $name;
        }

        $self->mode = null !== $self->default || $parameter->isDefaultValueAvailable() ? InputArgument::OPTIONAL : InputArgument::REQUIRED;
        if ('array' === $parameterTypeName) {
            $self->mode |= InputArgument::IS_ARRAY;
        }

        $self->default ??= $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

        if (\is_array($self->suggestedValues) && !\is_callable($self->suggestedValues) && 2 === \count($self->suggestedValues) && ($instance = $parameter->getDeclaringFunction()->getClosureThis()) && $instance::class === $self->suggestedValues[0] && \is_callable([$instance, $self->suggestedValues[1]])) {
            $self->suggestedValues = [$instance, $self->suggestedValues[1]];
        }

        return $self;
    }

    /**
     * @internal
     */
    public function toInputArgument(): InputArgument
    {
        $suggestedValues = \is_callable($this->suggestedValues) ? ($this->suggestedValues)(...) : $this->suggestedValues;

        return new InputArgument($this->name, $this->mode, $this->description, $this->default, $suggestedValues);
    }

    /**
     * @internal
     */
    public function resolveValue(InputInterface $input): mixed
    {
        return $input->hasArgument($this->name) ? $input->getArgument($this->name) : null;
    }
}
