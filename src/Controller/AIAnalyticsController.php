<?php

namespace App\Controller;

use App\Service\Analytics\MetricsService;
use App\Service\Analytics\NvidiaAIClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/ai')]
class AIAnalyticsController extends AbstractController
{
    /**
     * Define available tools (functions) for the AI.
     */
    private array $tools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_top_destinations',
                'description' => 'Get the most visited destinations (by voyage visit count).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'default' => 5]],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_all_users',
                'description' => 'Get a list of all users (id, username, email, phone, registration date). Limit 50.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'default' => 50]],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_voyages_with_most_reclamations',
                'description' => 'Get voyages that have the highest number of reclamations.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'default' => 5]],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_full_analytics_snapshot',
                'description' => 'Get a comprehensive 360° analytics snapshot including events, conversions, top voyages, user growth, reservations, payments, reclamations, refunds, offers and activities for a given period (default last 30 days). Use this when asked about general app health or "how is the app doing".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD), optional'],
                        'end_date' => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD), optional'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_event_type_distribution',
                'description' => 'Count events per type (search, voyage_visits, etc.) within a date range.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_search_to_view_conversion_rate',
                'description' => 'Conversion rate from search to voyage view.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_top_voyages_by_visits',
                'description' => 'Get top voyages ranked by number of visits.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'default' => 5]],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_user_growth_stats',
                'description' => 'Get user growth (total, new, growth rate) for a period.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_reservation_summary',
                'description' => 'Summary of reservations (total, confirmed, pending, cancelled, total revenue).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_payment_success_rate',
                'description' => 'Percentage of successful payments (PAID vs total).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_reclamation_summary',
                'description' => 'Summary of reclamations (open, in progress, resolved, high priority).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_refund_request_summary',
                'description' => 'Summary of refund requests (pending, approved, rejected, total approved amount).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'required' => ['start_date', 'end_date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_unmet_demand_destinations',
                'description' => 'Destinations that users searched for but are not offered in any voyage.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'default' => 5]],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_voyage_details',
                'description' => 'Get detailed information about a specific voyage by ID or title.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['identifier' => ['type' => 'string']],
                    'required' => ['identifier'],
                ],
            ],
        ],
    ];

    #[Route('/ask', name: 'ai_ask', methods: ['POST'])]
    public function ask(Request $request, MetricsService $metrics, NvidiaAIClient $ai): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? null;

        if (!$question) {
            return $this->json(['error' => 'No question provided'], 400);
        }

        // Pre‑fetch the full snapshot once – can be injected as system context
        // for general questions, but the AI can also call the tool explicitly.
        $snapshot = $metrics->getFullAnalyticsSnapshot();
        $snapshotJson = json_encode($snapshot, JSON_PRETTY_PRINT);

        $systemPrompt = <<<PROMPT
You are a Senior Travel Agency Analyst. You MUST use the provided tools to answer questions.

**RULES (read carefully):**
1. If the user asks for any data (conversion rate, top destinations, list of users, voyages with most reclamations, etc.), you MUST call the appropriate tool. Do NOT describe what the tool does – actually call it.
2. Your response must be a valid JSON object containing a "tool_calls" field when you need data. Example:
   {"tool_calls": [{"id": "call_1", "type": "function", "function": {"name": "get_top_destinations", "arguments": "{\"limit\": 5}"}}]}
3. After receiving tool results, you will output a natural language answer.
4. Never output text like "The function X returns..." – that is forbidden.

Available tools:
- get_full_analytics_snapshot
- get_top_destinations (limit)
- get_all_users (limit)
- get_voyages_with_most_reclamations (limit)
- get_voyage_details (identifier)
- get_search_to_view_conversion_rate (start_date, end_date)
- ... (all other tools)

Current snapshot (use only if user asks for general overview, otherwise call fresh tools):
{$snapshotJson}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $question]
        ];

        $toolResult = null;
        $toolName = null;
        $toolCallId = null;
        $toolUsed = false;

        // Agentic loop – maximum 2 turns (AI asks tool, then AI answers)
        for ($i = 0; $i < 2; $i++) {
            $response = $ai->chat($messages, $this->tools);
            $choice = $response['choices'][0]['message'] ?? null;

            if (!$choice) {
                return $this->json(['error' => 'No response from AI'], 500);
            }
            if (!isset($choice['tool_calls']) && isset($choice['content'])) {
    $fakeToolCalls = $this->extractToolCallFromContent($choice['content']);
    if ($fakeToolCalls) {
        // Replace content with empty string and inject tool_calls
        $choice['tool_calls'] = $fakeToolCalls;
        $choice['content'] = '';
    }
}

            // If AI wants to call a tool, execute and append result
            if (isset($choice['tool_calls'])) {
                $messages[] = $choice; // assistant message with tool_calls

                foreach ($choice['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $args = json_decode($toolCall['function']['arguments'], true) ?? [];
                    $toolName = $functionName;
                    $toolCallId = $toolCall['id'];
                    $toolUsed = true;

                    // Execute the requested tool
                    $toolResult = match ($functionName) {
                        'get_top_destinations' => $metrics->getTopDestinations($args['limit'] ?? 5),
                        'get_all_users' => $metrics->getAllUsers($args['limit'] ?? 50),
                        'get_voyages_with_most_reclamations' => $metrics->getVoyagesWithMostReclamations($args['limit'] ?? 5),
                        'get_full_analytics_snapshot' => $metrics->getFullAnalyticsSnapshot(
                            $args['start_date'] ?? null,
                            $args['end_date'] ?? null
                        ),
                        'get_event_type_distribution' => $metrics->getEventTypeDistribution(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_search_to_view_conversion_rate' => $metrics->getSearchToViewConversionRate(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_top_voyages_by_visits' => $metrics->getTopVoyagesByVisits(
                            $args['limit'] ?? 5
                        ),
                        'get_user_growth_stats' => $metrics->getUserGrowthStats(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_reservation_summary' => $metrics->getReservationSummary(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_payment_success_rate' => $metrics->getPaymentSuccessRate(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_reclamation_summary' => $metrics->getReclamationSummary(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_refund_request_summary' => $metrics->getRefundRequestSummary(
                            $args['start_date'],
                            $args['end_date']
                        ),
                        'get_unmet_demand_destinations' => $metrics->getUnmetDemandDestinations(
                            $args['limit'] ?? 5
                        ),
                        'get_voyage_details' => $metrics->getVoyageDetails($args['identifier']),
                        default => ['error' => "Tool '{$functionName}' not implemented"]
                    };

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'name' => $toolName,
                        'content' => json_encode($toolResult)
                    ];
                }
                continue; // Let the AI process the tool results in the next iteration
            }

            // No tool call – this is the final answer
            $aiContent = $choice['content'] ?? '';

            // If the AI didn't use a tool but the user asked for data,
            // we can optionally fall back to the snapshot.
            if (!$toolUsed && (stripos($question, 'snapshot') !== false || stripos($question, '360') !== false)) {
                // Return the pre‑fetched snapshot directly as a fallback
                return $this->json(['data' => $snapshot]);
            }

            // Return the AI's natural language answer
            return $this->json(['answer' => $aiContent]);
        }

        // If we exit the loop without a final answer, provide a readable summary
        if ($toolUsed && $toolResult !== null) {
            $summary = $this->formatAnalyticsResult($toolName, $toolResult);
            return $this->json([
                'summary' => $summary,
                'data'    => $toolResult
            ]);
        }

        return $this->json(['error' => 'The AI could not complete the request.'], 500);
    }

    /**
     * Convert raw analytics data into a human‑readable summary for admin users.
     */
    private function formatAnalyticsResult(string $toolName, array|string|float|int $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        return match ($toolName) {
            'get_full_analytics_snapshot' => $this->formatSnapshotSummary($result),
            'get_event_type_distribution' => "Event counts: " . json_encode($result),
            'get_search_to_view_conversion_rate' => sprintf(
                "Conversion rate: %.1f%% (%d searchers → %d viewers)",
                $result['conversion_rate'] ?? 0,
                $result['searchers'] ?? 0,
                $result['viewers'] ?? 0
            ),
            'get_top_voyages_by_visits' => "Top voyages: " . implode(', ', array_column($result, 'title')),
            'get_user_growth_stats' => sprintf(
                "Total users: %d, new in period: %d (growth %.1f%%)",
                $result['total_users'] ?? 0,
                $result['new_users'] ?? 0,
                $result['growth_rate'] ?? 0
            ),
            'get_reservation_summary' => sprintf(
                "Reservations: %d total, %d confirmed, %d pending, %d cancelled. Revenue: %.2f",
                $result['total_reservations'] ?? 0,
                $result['confirmed'] ?? 0,
                $result['pending'] ?? 0,
                $result['cancelled'] ?? 0,
                $result['total_revenue'] ?? 0
            ),
            'get_payment_success_rate' => "Payment success rate: {$result}%",
            'get_reclamation_summary' => sprintf(
                "Reclamations: %d open, %d in progress, %d resolved, %d high priority",
                $result['open'] ?? 0,
                $result['in_progress'] ?? 0,
                $result['resolved'] ?? 0,
                $result['high_priority'] ?? 0
            ),
            'get_refund_request_summary' => sprintf(
                "Refunds: %d pending, %d approved (total %.2f), %d rejected",
                $result['pending'] ?? 0,
                $result['approved'] ?? 0,
                $result['total_approved_amount'] ?? 0,
                $result['rejected'] ?? 0
            ),
            'get_unmet_demand_destinations' => "Unmet demand: " . implode(', ', array_column($result, 'destination')),
            default => json_encode($result),
        };
    }

    private function formatSnapshotSummary(array $snapshot): string
    {
        $lines = [];
        $lines[] = "📊 360° Analytics Snapshot";
        $lines[] = "Period: {$snapshot['period']['start']} → {$snapshot['period']['end']}";

        $conv = $snapshot['search_to_view_conversion'] ?? [];
        $lines[] = "🔍 Search→View conversion: {$conv['conversion_rate']}% ({$conv['viewers']} viewers / {$conv['searchers']} searchers)";

        $topVoyages = $snapshot['top_voyages_by_visits'] ?? [];
        if ($topVoyages) {
            $topNames = array_column(array_slice($topVoyages, 0, 3), 'title');
            $lines[] = "🏆 Top voyages: " . implode(', ', $topNames);
        }

        $users = $snapshot['user_growth'] ?? [];
        $lines[] = "👥 Users: {$users['total_users']} total, +{$users['new_users']} new ({$users['growth_rate']}% growth)";

        $res = $snapshot['reservation_summary'] ?? [];
        $lines[] = "💰 Reservations: {$res['confirmed']} confirmed, revenue {$res['total_revenue']}";

        $recl = $snapshot['reclamation_summary'] ?? [];
        $lines[] = "⚠️ Open reclamations: {$recl['open']}";

        return implode("\n", $lines);
    }
    /**
 * If the AI's content looks like a tool call JSON, parse it and return an array
 * that mimics the standard tool_calls structure.
 */
private function extractToolCallFromContent(string $content): ?array
{
    // Look for JSON containing "tool_calls"
    if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $match)) {
        $decoded = json_decode($match[0], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['tool_calls'])) {
            // Normalize to the format our loop expects
            return $decoded['tool_calls'];
        }
    }
    // Also try to extract a function call like: get_voyage_details(9)
    if (preg_match('/(\w+)\(([^)]+)\)/', $content, $matches)) {
        $funcName = $matches[1];
        $argsRaw = $matches[2];
        // Simple argument parsing (supports quoted strings and numbers)
        $args = [];
        if (is_numeric($argsRaw)) {
            $args = ['identifier' => $argsRaw];
        } elseif (preg_match('/[\'"](\d+)[\'"]/', $argsRaw, $idMatch)) {
            $args = ['identifier' => $idMatch[1]];
        } elseif (preg_match('/[\'"]([^\'"]+)[\'"]/', $argsRaw, $strMatch)) {
            $args = ['identifier' => $strMatch[1]];
        }
        return [[
            'id'   => 'fallback_' . uniqid(),
            'type' => 'function',
            'function' => [
                'name' => $funcName,
                'arguments' => json_encode($args)
            ]
        ]];
    }
    return null;
}
}
