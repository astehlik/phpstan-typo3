<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use SaschaEgerer\PhpstanTypo3\Service\RepositoryModelResolver;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;

class RepositoryQueryDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
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
		return $methodReflection->getName() === 'createQuery';
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

		$modelClass = $this->repositoryModelResolver->resolve($classReflections[0]);
		if ($modelClass === null) {
			return null;
		}

		return new GenericObjectType(QueryInterface::class, [new ObjectType($modelClass->getName())]);
	}

}
