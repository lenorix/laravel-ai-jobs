<?php

namespace Lenorix\LaravelAiJobs\Models;

use Lenorix\LaravelJobStatus\Models\JobTracker;
use MalteKuhr\LaravelGPT\GPTChat;
use MalteKuhr\LaravelGPT\Models\ChatMessage;
use OpenAI\Responses\Chat\CreateResponseMessage;

class GptChatFuture extends JobTracker
{
    protected $table = 'job_trackers';

    public function getResultIn(GPTChat $gptChat): void
    {
        if (!$this->isSuccessful()) {
            throw new \Exception('Job is not successful, cannot update messages.');
        }
        $gptChat->messages = array_map(function ($message) {
            return ChatMessage::fromResponseMessage(CreateResponseMessage::from($message));
        }, $this->result);
    }
}
