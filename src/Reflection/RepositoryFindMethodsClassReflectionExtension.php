<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Reflection;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\ShouldNotHappenException;
use SaschaEgerer\PhpstanTypo3\Service\RepositoryModelResolver;
use TYPO3\CMS\Extbase\Persistence\Repository;

class RepositoryFindMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{

	public function __construct(private readonly RepositoryModelResolver $repositoryModelResolver)
	{
	}

	public function hasMethod(ClassReflection $classReflection, string $methodName): bool
	{
		if (!in_array(Repository::class, $classReflection->getParentClassesNames(), true)) {
			return false;
		}

		if ($classReflection->hasNativeMethod($methodName)) {
			return false;
		}

		if ($classReflection->isAbstract()) {
			return false;
		}

		if (strpos($methodName, 'findOneBy') === 0) {
			$propertyName = lcfirst(substr($methodName, 9));
		} elseif (strpos($methodName, 'findBy') === 0) {
			$propertyName = lcfirst(substr($methodName, 6));
		} else {
			return false;
		}

		// ensure that a property with that name exists on the model, as there might
		// be methods starting with find[One]By... with custom implementations on
		// inherited repositories
		$modelReflection = $this->repositoryModelResolver->resolve($classReflection);

		return $modelReflection !== null && $modelReflection->hasProperty($propertyName);
	}

	public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
	{
		$modelReflection = $this->repositoryModelResolver->resolve($classReflection);
		if ($modelReflection === null) {
			throw new ShouldNotHappenException();
		}

		if (strpos($methodName, 'findOneBy') === 0) {
			return new RepositoryFindOneByMethodReflection($classReflection, $methodName, $modelReflection);
		}

		return new RepositoryFindByMethodReflection($classReflection, $methodName, $modelReflection);
	}

}
