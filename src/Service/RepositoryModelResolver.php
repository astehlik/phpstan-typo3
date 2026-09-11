<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Service;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Type;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;

/**
 * Determines the model class a repository manages, the same way Extbase does at runtime:
 * an explicit `$this->objectType = X::class` wins, then the `@extends Repository<X>`
 * annotation, then the naming convention (`Domain\Repository\XRepository` → `Domain\Model\X`).
 */
final class RepositoryModelResolver
{

	/** @var array<class-string, ?string> */
	private array $assignedObjectTypes = [];

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly Parser $parser
	)
	{
	}

	public function resolve(ClassReflection $repositoryClass): ?ClassReflection
	{
		$hierarchy = $this->getRepositoryHierarchy($repositoryClass);

		foreach ($hierarchy as $class) {
			$modelClass = $this->findExistingClass($this->findAssignedObjectType($class));
			if ($modelClass !== null) {
				return $modelClass;
			}
		}

		$templateType = $this->getRepositoryTemplateType($repositoryClass);
		if ($templateType instanceof TemplateType) {
			return null;
		}
		if ($templateType !== null) {
			$classNames = $templateType->getObjectClassNames();
			if (count($classNames) === 1 && $classNames[0] !== DomainObjectInterface::class) {
				$modelClass = $this->findExistingClass($classNames[0]);
				if ($modelClass !== null) {
					return $modelClass;
				}
			}
		}

		foreach ($hierarchy as $class) {
			if (!$class->implementsInterface(RepositoryInterface::class)) {
				continue;
			}
			/** @var class-string<RepositoryInterface<object>> $repositoryClassName */
			$repositoryClassName = $class->getName();
			$modelClass = $this->findExistingClass(
				ClassNamingUtility::translateRepositoryNameToModelName($repositoryClassName)
			);
			if ($modelClass !== null && $modelClass->implementsInterface(DomainObjectInterface::class)) {
				return $modelClass;
			}
		}

		return null;
	}

	/**
	 * @return list<ClassReflection>
	 */
	private function getRepositoryHierarchy(ClassReflection $repositoryClass): array
	{
		$hierarchy = [$repositoryClass];
		foreach ($repositoryClass->getParents() as $parent) {
			if ($parent->getName() === Repository::class) {
				break;
			}
			$hierarchy[] = $parent;
		}

		return $hierarchy;
	}

	private function getRepositoryTemplateType(ClassReflection $repositoryClass): ?Type
	{
		$repository = $repositoryClass->getAncestorWithClassName(Repository::class);
		if ($repository === null) {
			return null;
		}

		$types = $repository->getPossiblyIncompleteActiveTemplateTypeMap()->getTypes();

		return $types !== [] ? reset($types) : null;
	}

	private function findExistingClass(?string $className): ?ClassReflection
	{
		if ($className === null || !$this->reflectionProvider->hasClass($className)) {
			return null;
		}

		return $this->reflectionProvider->getClass($className);
	}

	private function findAssignedObjectType(ClassReflection $class): ?string
	{
		$className = $class->getName();
		if (!array_key_exists($className, $this->assignedObjectTypes)) {
			$this->assignedObjectTypes[$className] = $this->parseAssignedObjectType($class);
		}

		return $this->assignedObjectTypes[$className];
	}

	private function parseAssignedObjectType(ClassReflection $class): ?string
	{
		$fileName = $class->getFileName();
		if ($fileName === null) {
			return null;
		}

		try {
			$nodes = $this->parser->parseFile($fileName);
		} catch (\Exception) {
			return null;
		}

		$nodeFinder = new NodeFinder();
		$classNode = $nodeFinder->findFirst($nodes, static fn (Node $node): bool => $node instanceof Class_
			&& $node->namespacedName !== null
			&& $node->namespacedName->toString() === $class->getName());
		if (!$classNode instanceof Class_) {
			return null;
		}

		foreach ($classNode->getProperties() as $property) {
			$objectType = $this->getObjectTypeFromPropertyDefault($property);
			if ($objectType !== null) {
				return $objectType;
			}
		}

		$assignments = $nodeFinder->find($classNode->getMethods(), static fn (Node $node): bool => $node instanceof Assign
			&& $node->var instanceof PropertyFetch
			&& $node->var->var instanceof Variable
			&& $node->var->var->name === 'this'
			&& $node->var->name instanceof Identifier
			&& $node->var->name->toString() === 'objectType');
		foreach ($assignments as $assignment) {
			if (!$assignment instanceof Assign) {
				continue;
			}
			$objectType = $this->getClassNameFromExpression($assignment->expr);
			if ($objectType !== null) {
				return $objectType;
			}
		}

		return null;
	}

	private function getObjectTypeFromPropertyDefault(Property $property): ?string
	{
		foreach ($property->props as $prop) {
			if ($prop->name->toString() !== 'objectType' || $prop->default === null) {
				continue;
			}

			return $this->getClassNameFromExpression($prop->default);
		}

		return null;
	}

	private function getClassNameFromExpression(Node\Expr $expression): ?string
	{
		if ($expression instanceof String_) {
			return ltrim($expression->value, '\\');
		}
		if (
			$expression instanceof ClassConstFetch
			&& $expression->class instanceof Name
			&& !$expression->class->isSpecialClassName()
			&& $expression->name instanceof Identifier
			&& $expression->name->toLowerString() === 'class'
		) {
			return $expression->class->toString();
		}

		return null;
	}

}
