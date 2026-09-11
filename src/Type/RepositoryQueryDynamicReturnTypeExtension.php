<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use SaschaEgerer\PhpstanTypo3\Helpers\Typo3ClassNamingUtilityTrait;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;

class RepositoryQueryDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	use Typo3ClassNamingUtilityTrait;

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
	): Type
	{
		$queryType = $scope->getType($methodCall->var);
		if ($queryType instanceof GenericObjectType) {
			$modelType = $queryType->getTypes();
		} else {
			$classReflections = $queryType->getObjectClassReflections();

			if (count($classReflections) !== 1) {
				return new ErrorType();
			}

			// A generic repository (e.g. an abstract base with @template) has no model class to derive
			// from its name; the declared return type already carries the template type.
			if ($classReflections[0]->isGeneric()) {
				return $methodReflection->getVariants()[0]->getReturnType();
			}

			/** @var class-string $className */
			$className = $classReflections[0]->getName();

			$modelName = $this->translateRepositoryNameToModelName($className);

			$modelType = [new ObjectType($modelName)];
		}

		return new GenericObjectType(QueryInterface::class, $modelType);
	}

}
