<?php

namespace Phpactor\WorseReflection\Bridge\Phpactor\DocblockParser;

use Phpactor\DocblockParser\Ast\Docblock as ParserDocblock;
use Phpactor\DocblockParser\Ast\ParameterList;
use Phpactor\DocblockParser\Ast\Tag\DeprecatedTag;
use Phpactor\DocblockParser\Ast\Tag\ExtendsTag;
use Phpactor\DocblockParser\Ast\Tag\ImplementsTag;
use Phpactor\DocblockParser\Ast\Tag\MethodTag;
use Phpactor\DocblockParser\Ast\Tag\MixinTag;
use Phpactor\DocblockParser\Ast\Tag\ParamTag;
use Phpactor\DocblockParser\Ast\Tag\ParameterTag;
use Phpactor\DocblockParser\Ast\Tag\PropertyTag;
use Phpactor\DocblockParser\Ast\Tag\ReturnTag;
use Phpactor\DocblockParser\Ast\Tag\TemplateTag;
use Phpactor\DocblockParser\Ast\Tag\VarTag;
use Phpactor\DocblockParser\Ast\TypeNode;
use Phpactor\WorseReflection\Core\DefaultValue;
use Phpactor\WorseReflection\Core\Deprecation;
use Phpactor\WorseReflection\Core\DocBlock\DocBlock;
use Phpactor\WorseReflection\Core\DocBlock\DocBlockVar;
use Phpactor\WorseReflection\Core\Inference\Frame;
use Phpactor\WorseReflection\Core\NodeText;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionParameterCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionMethodCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionPropertyCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionPropertyCollection as CoreReflectionPropertyCollection;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionMethodCollection as CoreReflectionMethodCollection;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClassLike;
use Phpactor\WorseReflection\Core\TemplateMap;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Core\DocBlock\DocBlockVars;
use Phpactor\WorseReflection\Core\Virtual\VirtualReflectionMethod;
use Phpactor\WorseReflection\Core\Virtual\VirtualReflectionParameter;
use Phpactor\WorseReflection\Core\Virtual\VirtualReflectionProperty;
use Phpactor\WorseReflection\Core\Visibility;
use function array_map;

class ParsedDocblock implements DocBlock
{
    private ParserDocblock $node;

    private TypeConverter $typeConverter;

    public function __construct(ParserDocblock $node, TypeConverter $typeConverter)
    {
        $this->node = $node;
        $this->typeConverter = $typeConverter;
    }

    public function methodType(string $methodName): Type
    {
        foreach ($this->node->descendantElements(MethodTag::class) as $methodTag) {
            assert($methodTag instanceof MethodTag);
            if ($methodTag->methodName() !== $methodName) {
                continue;
            }
            $this->typeConverter->convert($methodTag->type);
        }

        return TypeFactory::undefined();
    }

    public function inherits(): bool
    {
        return false;
    }

    public function vars(): DocBlockVars
    {
        $vars = [];
        foreach ($this->node->descendantElements(VarTag::class) as $varTag) {
            assert($varTag instanceof VarTag);
            $vars[] = new DocBlockVar(
                $varTag->variable ? ltrim($varTag->name() ?? '', '$') : '',
                $this->typeConverter->convert($varTag->type),
            );
        }

        return new DocBlockVars($vars);
    }

    public function parameterType(string $paramName): Type
    {
        $types = [];
        foreach ($this->node->descendantElements(ParamTag::class) as $paramTag) {
            assert($paramTag instanceof ParamTag);
            if (ltrim($paramTag->paramName(), '$') !== $paramName) {
                continue;
            }
            return $this->typeConverter->convert($paramTag->type);
        }

        return TypeFactory::undefined();
    }

    public function propertyType(string $propertyName): Type
    {
        $types = [];
        foreach ($this->node->descendantElements(PropertyTag::class) as $propertyTag) {
            assert($propertyTag instanceof PropertyTag);
            if (ltrim($propertyTag->propertyName(), '$') !== $propertyName) {
                continue;
            }
            return $this->typeConverter->convert($propertyTag->type);
        }

        return TypeFactory::undefined();
    }

    public function formatted(): string
    {
        return implode("\n", array_map(function (string $line) {
            return preg_replace('{^\s+}', '', $line);
        }, explode("\n", $this->node->prose())));
    }

    public function returnType(): Type
    {
        foreach ($this->node->descendantElements(ReturnTag::class) as $tag) {
            assert($tag instanceof ReturnTag);
            return $this->typeConverter->convert($tag->type());
        }

        return TypeFactory::undefined();
    }

    public function raw(): string
    {
        return $this->node->toString();
    }

    public function isDefined(): bool
    {
        return true;
    }

    public function properties(ReflectionClassLike $declaringClass): CoreReflectionPropertyCollection
    {
        $properties = [];
        foreach ($this->node->descendantElements(PropertyTag::class) as $propertyTag) {
            assert($propertyTag instanceof PropertyTag);
            $type = $this->typeConverter->convert($propertyTag->type);
            $property = new VirtualReflectionProperty(
                $declaringClass->position(),
                $declaringClass,
                $declaringClass,
                ltrim($propertyTag->propertyName() ?? '', '$'),
                new Frame('docblock'),
                $this,
                $declaringClass->scope(),
                Visibility::public(),
                $type,
                $type,
                new Deprecation(false),
            );
            $properties[] = $property;
        }

        return ReflectionPropertyCollection::fromReflectionProperties($properties);
    }

    public function methods(ReflectionClassLike $declaringClass): CoreReflectionMethodCollection
    {
        $methods = [];
        foreach ($this->node->descendantElements(MethodTag::class) as $methodTag) {
            assert($methodTag instanceof MethodTag);
            $params = ReflectionParameterCollection::empty();
            $method = new VirtualReflectionMethod(
                $declaringClass->position(),
                $declaringClass,
                $declaringClass,
                $methodTag->methodName() ?? '',
                new Frame('docblock'),
                $this,
                $declaringClass->scope(),
                Visibility::public(),
                $this->typeConverter->convert($methodTag->type),
                $this->typeConverter->convert($methodTag->type),
                $params,
                NodeText::fromString(''),
                false,
                false,
                new Deprecation(false),
            );
            $this->addParameters($method, $params, $methodTag->parameters);
            $methods[] = $method;
        }

        return ReflectionMethodCollection::fromReflectionMethods($methods);
    }

    public function deprecation(): Deprecation
    {
        foreach ($this->node->descendantElements(DeprecatedTag::class) as $deprecatedTag) {
            assert($deprecatedTag instanceof DeprecatedTag);
            return new Deprecation(true, $deprecatedTag->text());
        }

        return new Deprecation(false);
    }

    public function templateMap(): TemplateMap
    {
        $map = [];
        foreach ($this->node->descendantElements(TemplateTag::class) as $templateTag) {
            assert($templateTag instanceof TemplateTag);
            $map[$templateTag->placeholder()] = $this->typeConverter->convert($templateTag->type);
        }
        return new TemplateMap($map);
    }

    public function extends(): array
    {
        $extends = [];
        foreach ($this->node->descendantElements(ExtendsTag::class) as $extendsTag) {
            assert($extendsTag instanceof ExtendsTag);
            $extends[] = $this->typeConverter->convert($extendsTag->type);
        }
        return $extends;
    }

    public function implements(): array
    {
        $implements = [];
        foreach ($this->node->descendantElements(ImplementsTag::class) as $implementsTag) {
            assert($implementsTag instanceof ImplementsTag);
            $implements = array_merge($implements, array_map(function (TypeNode $type) {
                return $this->typeConverter->convert($type);
            }, $implementsTag->types()));
        }
        return $implements;
    }

    public function mixins(): array
    {
        $mixins = [];
        foreach ($this->node->descendantElements(MixinTag::class) as $mixinTag) {
            assert($mixinTag instanceof MixinTag);
            $mixins[] = $this->typeConverter->convert($mixinTag->class);
        }
        return $mixins;
    }

    private function addParameters(VirtualReflectionMethod $method, ReflectionParameterCollection $collection, ?ParameterList $parameterList): void
    {
        if (null === $parameterList) {
            return;
        }
        foreach ($parameterList->parameters() as $parameterTag) {
            assert($parameterTag instanceof ParameterTag);
            $type = $this->typeConverter->convert($parameterTag->type);
            $collection->add(new VirtualReflectionParameter(
                ltrim($parameterTag->parameterName() ?? '', '$'),
                $method,
                $type,
                $type,
                DefaultValue::undefined(),
                false,
                $method->scope(),
                $method->position()
            ));
        }
    }
}
