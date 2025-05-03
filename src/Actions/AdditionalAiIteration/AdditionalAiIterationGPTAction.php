<?php

namespace Lenorix\LaravelAiJobs\Actions\AdditionalAiIteration;

use Closure;
use MalteKuhr\LaravelGPT\GPTAction;

class AdditionalAiIterationGPTAction extends GPTAction
{
    public function systemMessage(): ?string
    {
        return <<<'EOT'
            Based on the message, decide if an additional AI iteration is required.

            Return `true` when:
            - The reply implies a future action, side effect, or tool invocation.
            - The reply could be enhanced with proactive details.
            - The reply is empty or a placeholder.

            Return `false` when:
            - The reply is purely informational and complete.

            Triggering patterns for `true`:
            - Replies starting with: `I will`, `Let me`, `Searching`, `Collecting`, `Getting`, `Looking`
            - Empty string or generic placeholders (e.g., `...`, `Hold on`)

            Examples:

            Input: "I will search the database for user data."
            Output: `true`

            Input: "Here’s the summary you requested."
            Output: `false`

            Input: "I will search ..."
            Output: `true`

            Input: "I will do ..."
            Output: `true`

            Input: "Let me think ..."
            Output: `true`

            Input: "Let me do ..."
            Output: `true`

            Input: "Let me know ..."
            Output: `false`

            Input: "Looking for ..."
            Output: `true`

            Input: "Searching ..."
            Output: `true`

            Input: "Collecting ..."
            Output: `true`

            Input: "Getting ..."
            Output: `true`

            Input: ""
            Output: `true`

            Input: "You're welcome! If you have any more questions ... don't hesitate to let me know. I'm here to help!"
            Output: `false`

            Input: "Let me know if you need anything else."
            Output: `false`
        EOT;

    }

    public function function(): Closure
    {
        return function (bool $additionalIteration = false): mixed {
            return $additionalIteration;
        };
    }

    public function temperature(): ?float
    {
        return 0.1;
    }

    public function rules(): array
    {
        return [
            'additionalIteration' => 'required|boolean',
        ];
    }
}
