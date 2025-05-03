<?php

namespace Lenorix\LaravelAiJobs\Actions\ReplyOrQueueAiJob;

use Closure;
use Lenorix\LaravelAiJobs\Actions\AdditionalAiIteration\AdditionalAiIterationGPTAction;
use MalteKuhr\LaravelGPT\Enums\ChatRole;
use MalteKuhr\LaravelGPT\Extensions\FillableGPTChat;
use MalteKuhr\LaravelGPT\Extensions\FillableGPTFunction;
use MalteKuhr\LaravelGPT\Generators\ChatPayloadGenerator;
use MalteKuhr\LaravelGPT\GPTAction;
use MalteKuhr\LaravelGPT\GPTChat;

class ReplyOrQueueAiJobGPTAction extends GPTAction
{
    public function __construct(
        protected GPTChat $gptChat,
    ) {}

    public function systemMessage(): ?string
    {
        $functions = ChatPayloadGenerator::make($this->gptChat)->generate()['functions'] ?? [];

        return $this->gptChat->systemMessage() ?? ' '
            .<<<'EOT'

            ## Decide if needs to queue a job

            You must always decide:
            - `queue`: **true** or **false**
            - `message`: the assistant’s reply

            **Rule:** if you plan to call any function/tool, you **must** set `queue` to `true`.

            Functions available when `queue` is true:
        EOT
            .json_encode($functions)
            .<<<'EOT'

            Examples:

            Input: "I will search the database for user data"
            Output:
            {
              "queue": true,
              "message": "Let me fetch that for you..."
            }

            Input: "What’s the weather today?"
            Output:
            {
              "queue": false,
              "message": "The weather is sunny."
            }
        EOT;
    }

    public function function(): Closure
    {
        return function (bool $queue = true, ?string $message = null): mixed {
            return [
                'queue' => $queue !== false,
                'message' => $message,
            ];
        };
    }

    public function temperature(): ?float
    {
        // Less temperature helps to detect if the assistant is in a infinite loop.
        return 0.25;
    }

    public function rules(): array
    {
        return [
            'queue' => 'required|boolean',
            'message' => 'sometimes|string',
        ];
    }

    public function sendToDecide(): mixed
    {
        $this->chat = FillableGPTChat::make(
            systemMessage: fn () => $this->systemMessage(),
            functions: fn () => [
                new FillableGPTFunction(
                    name: fn () => $this->functionName(),
                    description: fn () => $this->description(),
                    function: fn () => $this->function(),
                    rules: fn () => $this->rules(),
                ),
            ],
            functionCall: fn () => FillableGPTFunction::class,
            model: fn () => $this->model(),
            temperature: fn () => $this->temperature(),
            maxTokens: fn () => $this->maxTokens(),
            sending: fn () => $this->sending(),
            received: fn () => $this->received(),
        );

        $this->chat->messages = $this->gptChat->messages;
        $this->chat->send();

        // Avoid infinite loop if the assistant replies the same message.
        $previousAssistantMessage = array_slice(array_filter($this->gptChat->messages, function ($message) {
            return $message->role == ChatRole::ASSISTANT;
        }), -1, 1)[0]->content ?? null;
        $latestAssistantMessage = $this->chat->latestMessage()->content;
        if ($previousAssistantMessage == ($latestAssistantMessage['message'] ?? null)) {
            return [
                'queue' => true,
                'message' => null,
            ];
        }

        // Review with AI if the AI is doing well deciding about queuing while thinking fast response.
        if (! $latestAssistantMessage['queue']) {
            try {
                // Why? This one use original system prompt for AI can reply and consider the original task,
                // but that even needed that can let to answers with wrong queue decision. Then other
                // request decide better with a specialized system prompt and only the latest AI answer.
                $reviewQueueDecision = AdditionalAiIterationGPTAction::make()
                    ->send($latestAssistantMessage['message'] ?? '');
            } catch (\Exception $e) {
                // In case of doubt, better queue job and let use all tools and functions.
                $reviewQueueDecision = true;
            }
            $latestAssistantMessage['queue'] = $reviewQueueDecision;
        }

        return $latestAssistantMessage;
    }
}
