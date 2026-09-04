<?php declare(strict_types = 1);

namespace SaschaEgerer\PhpstanTypo3\Service;

use PHPStan\Type\Type;

final class ValidatorClassNameResolver
{

	public function resolve(Type $type): ?string
	{
		$constantStrings = $type->getConstantStrings();

		if ($constantStrings === []) {
			return null;
		}

		return \TYPO3\CMS\Extbase\Validation\ValidatorClassNameResolver::resolve($constantStrings[0]->getValue());
	}

}
