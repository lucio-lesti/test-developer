<?php

declare(strict_types=1);

namespace App\Application\Actions\ProblemSolving;

use App\Application\Actions\Action;
use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\ProblemSolving\EventGrouper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class GroupEventsAction extends Action
{
    private EventGrouper $grouper;

    public function __construct(LoggerInterface $logger, EventGrouper $grouper)
    {
        parent::__construct($logger);
        $this->grouper = $grouper;
    }

    protected function action(): Response
    {
        $body = $this->getFormData();
        if (!is_array($body) || !isset($body['events']) || !is_array($body['events'])) {
            return $this->respond(new ActionPayload(
                422,
                ['fields' => ['events' => ['Must be an array of {user_id, event} objects.']]],
                new ActionError(ActionError::VALIDATION_ERROR, 'Invalid input.')
            ));
        }

        $grouped = $this->grouper->group($body['events']);
        return $this->respondWithData(['groups' => $grouped]);
    }
}
