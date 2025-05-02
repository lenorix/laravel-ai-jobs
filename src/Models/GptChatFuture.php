<?php

namespace Lenorix\LaravelAiJobs\Models;

use Lenorix\LaravelJobStatus\Models\JobTracker;
use MalteKuhr\LaravelGPT\Enums\ChatRole;
use MalteKuhr\LaravelGPT\GPTChat;
use MalteKuhr\LaravelGPT\Models\ChatFunctionCall;
use MalteKuhr\LaravelGPT\Models\ChatMessage;
use OpenAI\Responses\Chat\CreateResponseMessage;

class GptChatFuture extends JobTracker
{
    protected $table = 'job_trackers';

    public function getResultIn(GPTChat $gptChat): void
    {
        if (! $this->isSuccessful()) {
            throw new \Exception('Job is not successful, cannot update messages.');
        }
        $gptChat->messages = array_map(function ($message) {
            if (is_array($message['content'])) {
                $message['content'] = json_encode($message['content']);
            }
            $name = $message['name'] ?? null;
            $message = CreateResponseMessage::from($message);
            return ChatMessage::from(
                role: ChatRole::from($message->role),
                content: $message->content,
                name: $name,
                functionCall: $message->functionCall ? ChatFunctionCall::fromResponseFunctionCall($message->functionCall) : null
            );
        }, $this->result);
    }
}
