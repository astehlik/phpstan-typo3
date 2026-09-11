<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Reflection;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class RepositoryFindByMethodReflection implements MethodReflection
{

	public function __construct(
		private readonly ClassReflection $classReflection,
		private readonly string $name,
		private readonly ClassReflection $modelReflection
	)
	{
	}

	public function getDeclaringClass(): ClassReflection
	{
		return $this->classReflection;
	}

	public function isStatic(): bool
	{
		return false;
	}

	public function isPrivate(): bool
	{
		return false;
	}

	public function isPublic(): bool
	{
		return true;
	}

	public function getPrototype(): ClassMemberReflection
	{
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	private function getPropertyName(): string
	{
		return lcfirst(substr($this->getName(), 6));
	}

	/**
	 * @return list<RepositoryFindByParameterReflection>
	 */
	public function getParameters(): array
	{
		if ($this->modelReflection->hasNativeProperty($this->getPropertyName())) {
			$type = $this->modelReflection->getNativeProperty($this->getPropertyName())->getReadableType();
		} else {
			$type = new MixedType(\false);
		}

		return [
			new RepositoryFindByParameterReflection('arg', $type),
		];
	}

	public function isVariadic(): bool
	{
		return false;
	}

	public function getReturnType(): GenericObjectType
	{
		return new GenericObjectType(QueryResultInterface::class, [new ObjectType($this->modelReflection->getName())]);
	}

	/**
	 * @return list<ParametersAcceptor>
	 */
	public function getVariants(): array
	{
		return [
			new FunctionVariant(
				TemplateTypeMap::createEmpty(),
				null,
				$this->getParameters(),
				$this->isVariadic(),
				$this->getReturnType()
			),
		];
	}

	public function getDocComment(): ?string
	{
		return null;
	}

	public function isDeprecated(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getDeprecatedDescription(): ?string
	{
		return null;
	}

	public function isFinal(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function isInternal(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getThrowType(): ?Type
	{
		return null;
	}

	public function hasSideEffects(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

}
