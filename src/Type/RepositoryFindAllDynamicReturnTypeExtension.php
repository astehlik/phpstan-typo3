<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use SaschaEgerer\PhpstanTypo3\Service\RepositoryModelResolver;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;

class RepositoryFindAllDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	public function __construct(private readonly RepositoryModelResolver $repositoryModelResolver)
	{
	}

	public function getClass(): string
	{
		return RepositoryInterface::class;
	}

	public function isMethodSupported(
		MethodReflection $methodReflection
	): bool
	{
		return $methodReflection->getName() === 'findAll';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): ?Type
	{
		$classReflections = $scope->getType($methodCall->var)->getObjectClassReflections();
		if (count($classReflections) !== 1) {
			return null;
		}

		$methodReturnType = $methodReflection->getVariants()[0]->getReturnType();
		$isDefaultFindAll = $methodReflection->getDeclaringClass()->getName() === Repository::class;
		if (!$isDefaultFindAll && !(new ObjectType(QueryResultInterface::class))->isSuperTypeOf($methodReturnType)->yes()) {
			return null;
		}

		$modelType = $methodReturnType->getIterableValueType();
		if ($modelType instanceof ObjectType && $modelType->getClassName() !== DomainObjectInterface::class) {
			// The declared type already knows the model, e.g. via @extends Repository<Model>.
			// The default implementation never returns the raw array variant, so narrow it.
			return $isDefaultFindAll ? $this->createQueryResultType($modelType) : null;
		}

		$modelClass = $this->repositoryModelResolver->resolve($classReflections[0]);
		if ($modelClass === null) {
			return null;
		}

		return $this->createQueryResultType(new ObjectType($modelClass->getName()));
	}

	private function createQueryResultType(Type $modelType): Type
	{
		return new GenericObjectType(QueryResultInterface::class, [new IntegerType(), $modelType]);
	}

}
