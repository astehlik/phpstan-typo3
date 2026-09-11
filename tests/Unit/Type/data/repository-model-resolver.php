<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.Namespaces.RequireOneNamespaceInFile.MoreNamespacesInFile
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

namespace RepositoryModelResolver\Vendor\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Answer extends AbstractEntity
{

	/** @var string */
	protected $field = '';

}

class Mail extends AbstractEntity
{

}

namespace RepositoryModelResolver\Vendor\Domain\Repository;

use RepositoryModelResolver\Vendor\Domain\Model\Answer;
use RepositoryModelResolver\Vendor\Domain\Model\Mail;
use TYPO3\CMS\Extbase\Persistence\Repository;

/** @phpstan-ignore-next-line */
abstract class AbstractRepository extends Repository
{

}

class AnswerRepository extends AbstractRepository
{

	public function initializeObject(): void
	{
		$this->objectType = Answer::class;
	}

}

/** @phpstan-ignore-next-line */
class MailNoExtendsRepository extends Repository
{

	public function __construct()
	{
		$this->objectType = Mail::class;
	}

}

/** @phpstan-ignore-next-line */
class MailByPropertyDefaultRepository extends Repository
{

	protected $objectType = Mail::class;

}

/** @phpstan-ignore-next-line */
class MailByStringRepository extends Repository
{

	public function __construct()
	{
		$this->objectType = '\RepositoryModelResolver\Vendor\Domain\Model\Mail';
	}

}

namespace RepositoryModelResolver\Site\Domain\Repository;

use RepositoryModelResolver\Vendor\Domain\Model\Answer;
use RepositoryModelResolver\Vendor\Domain\Repository\AnswerRepository as AnswerRepositoryVendor;
use function PHPStan\Testing\assertType;

class AnswerRepository extends AnswerRepositoryVendor
{

	public function __construct()
	{
		$this->objectType = Answer::class;
	}

	public function myTests(): void
	{
		$query = $this->createQuery();
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $query);
		assertType('TYPO3\CMS\Extbase\Persistence\QueryResultInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $this->findAll());
		assertType('RepositoryModelResolver\Vendor\Domain\Model\Answer|null', $this->findByUid(1));
		assertType('TYPO3\CMS\Extbase\Persistence\QueryResultInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $this->findByField('a'));
		assertType('RepositoryModelResolver\Vendor\Domain\Model\Answer|null', $this->findOneByField('a'));
		assertType('int', $this->countByField('a'));
		assertType('*ERROR*', $this->findByNonexisting('a'));
	}

}

/**
 * Inherits the objectType assignment from the parent constructor.
 */
class InheritingAnswerRepository extends AnswerRepositoryVendor
{

	public function myTests(): void
	{
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $this->createQuery());
	}

}

/**
 * Neither an objectType assignment nor an existing model by naming convention.
 */
class UnresolvableRepository extends \RepositoryModelResolver\Vendor\Domain\Repository\AbstractRepository
{

	public function myTests(): void
	{
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface>', $this->createQuery());
		assertType('TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface|null', $this->findByUid(1));
		assertType('*ERROR*', $this->findByField('a'));
	}

}

class Consumer
{

	public function __construct(
		private readonly \RepositoryModelResolver\Vendor\Domain\Repository\MailNoExtendsRepository $mailNoExtendsRepository,
		private readonly \RepositoryModelResolver\Vendor\Domain\Repository\MailByPropertyDefaultRepository $mailByPropertyDefaultRepository,
		private readonly \RepositoryModelResolver\Vendor\Domain\Repository\MailByStringRepository $mailByStringRepository,
		private readonly \RepositoryModelResolver\Vendor\Domain\Repository\AnswerRepository $vendorAnswerRepository,
		private readonly AnswerRepository $siteAnswerRepository
	)
	{
	}

	public function myTests(): void
	{
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Mail>', $this->mailNoExtendsRepository->createQuery());
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Mail>', $this->mailByPropertyDefaultRepository->createQuery());
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Mail>', $this->mailByStringRepository->createQuery());
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $this->vendorAnswerRepository->createQuery());
		assertType('TYPO3\CMS\Extbase\Persistence\QueryInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $this->siteAnswerRepository->createQuery());
		assertType('TYPO3\CMS\Extbase\Persistence\QueryResultInterface<RepositoryModelResolver\Vendor\Domain\Model\Answer>', $this->siteAnswerRepository->findAll());
		assertType('RepositoryModelResolver\Vendor\Domain\Model\Answer|null', $this->siteAnswerRepository->findByUid(1));
	}

}
