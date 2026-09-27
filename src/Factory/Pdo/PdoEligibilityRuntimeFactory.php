<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Factory\Pdo;

use Maatify\Eligibility\Evaluation\Repository\Pdo\PdoActiveRuleReader;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Management\Repository\Pdo\PdoRuleCommandRepository;
use Maatify\Eligibility\Management\Repository\Pdo\PdoRuleManagementQuery;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Management\Service\EligibilityManagementServiceInterface;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use PDO;

/**
 * Provides the package-wide default direct-PDO construction path for both
 * Eligibility capabilities over a caller-owned connection.
 */
final readonly class PdoEligibilityRuntimeFactory
{
    /**
     * Keeps the caller-owned PDO as the sole connection used by each graph.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Builds a default Management service graph over the caller-owned PDO.
     */
    public function createManagementService(): EligibilityManagementServiceInterface
    {
        $commandRepository = new PdoRuleCommandRepository($this->pdo);

        return new EligibilityManagementService(
            $commandRepository,
            new PdoRuleManagementQuery($this->pdo),
            $commandRepository,
            new PdoSavepointTransactionRunner($this->pdo),
        );
    }

    /**
     * Builds a default Evaluation service graph over the caller-owned PDO.
     */
    public function createEvaluationService(): EligibilityEvaluationServiceInterface
    {
        return new EligibilityEvaluationService(new PdoActiveRuleReader($this->pdo));
    }
}
