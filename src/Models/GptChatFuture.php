<?php

namespace Lenorix\LaravelAiJobs\Models;

use Lenorix\Ai\Chat\CoreMessage;
use Lenorix\Ai\Chat\CoreMessageRole;
use Lenorix\LaravelJobStatus\Models\JobTracker;
use MalteKuhr\LaravelGPT\Enums\ChatRole;
use MalteKuhr\LaravelGPT\Shim\GPTChatShim;

class GptChatFuture extends JobTracker
{
    protected $table = 'job_trackers';

    /**
     * Use the result of a job to update messages
     *  in a `GPTChat` instance.
     *
     * @throw If the job result is null or empty, as it's not available.
     */
    public function getResultIn(GPTChatShim $gptChat): void
    {
        if (! $this->result) {
            throw new \Exception('Job result is not available.');
        }

        $gptChat->messages = array_filter(array_map(function ($message) {
            if (is_array($message['content'])) {
                $message['content'] = json_encode($message['content']);
            }
            $role = $message['role'];
            if ($role === ChatRole::FUNCTION->value) {
                return null;
            }

            return new CoreMessage(
                role: CoreMessageRole::from($role),
                content: $message['content'],
                toolCalls: $message['toolCalls'] ?? null,
            );
        }, $this->result), fn ($i) => $i !== null);
    }
}
