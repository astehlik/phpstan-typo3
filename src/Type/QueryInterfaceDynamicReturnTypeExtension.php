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
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * A query without a type argument that is executed inside a repository targets the model of that repository.
 * Queries with a type argument are covered by the declared return type of execute().
 */
class QueryInterfaceDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	public function __construct(private readonly RepositoryModelResolver $repositoryModelResolver)
	{
	}

	public function getClass(): string
	{
		return QueryInterface::class;
	}

	public function isMethodSupported(
		MethodReflection $methodReflection
	): bool
	{
		return $methodReflection->getName() === 'execute';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): ?Type
	{
		if ($scope->getType($methodCall->var) instanceof GenericObjectType) {
			return null;
		}

		$argument = $methodCall->getArgs()[0] ?? null;
		if ($argument !== null && !$scope->getType($argument->value)->isFalse()->yes()) {
			return null;
		}

		$classReflection = $scope->getClassReflection();
		if ($classReflection === null || $classReflection->getAncestorWithClassName(Repository::class) === null) {
			return null;
		}

		$modelClass = $this->repositoryModelResolver->resolve($classReflection);
		if ($modelClass === null) {
			return null;
		}

		return new GenericObjectType(QueryResultInterface::class, [new IntegerType(), new ObjectType($modelClass->getName())]);
	}

}
