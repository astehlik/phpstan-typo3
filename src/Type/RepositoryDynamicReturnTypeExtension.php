<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use SaschaEgerer\PhpstanTypo3\Helpers\Typo3ClassNamingUtilityTrait;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;

class RepositoryDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
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
		return in_array($methodReflection->getName(), ['findByUid', 'findByIdentifier'], true);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$classReflections = $scope->getType($methodCall->var)->getObjectClassReflections();

		if (count($classReflections) !== 1
			|| $classReflections[0]->getName() === RepositoryInterface::class
			|| !$classReflections[0]->implementsInterface(RepositoryInterface::class)) {
			return $methodReflection->getVariants()[0]->getReturnType();
		}

		$modelName = $this->translateRepositoryNameToModelName($classReflections[0]->getName());

		return TypeCombinator::addNull(new ObjectType($modelName));
	}

}
