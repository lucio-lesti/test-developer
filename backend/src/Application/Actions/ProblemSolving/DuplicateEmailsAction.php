<?php

declare(strict_types=1);

namespace App\Application\Actions\ProblemSolving;

use App\Application\Actions\Action;
use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\ProblemSolving\DuplicateEmailFinder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class DuplicateEmailsAction extends Action
{
    private DuplicateEmailFinder $finder;

    public function __construct(LoggerInterface $logger, DuplicateEmailFinder $finder)
    {
        parent::__construct($logger);
        $this->finder = $finder;
    }

    protected function action(): Response
    {
        $body = $this->getFormData();
        if (!is_array($body) || !isset($body['emails']) || !is_array($body['emails'])) {
            return $this->respond(new ActionPayload(
                422,
                ['fields' => ['emails' => ['Must be an array of strings.']]],
                new ActionError(ActionError::VALIDATION_ERROR, 'Invalid input.')
            ));
        }

        $duplicates = $this->finder->findDuplicates($body['emails']);
        return $this->respondWithData(['duplicates' => $duplicates]);
    }
}
