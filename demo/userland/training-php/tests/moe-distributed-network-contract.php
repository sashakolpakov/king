<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/TrainingControlPlane.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$run = Training::distributed('agent-finetune-run-2026-05-05')
    ->model([
        'type' => 'moe',
        'name' => 'agent-finetune-contract-model',
        'experts' => 24,
        'router' => 'top2',
    ])
    ->data(
        Training\Data::objectStore('king://datasets/agents/finetune/v3')
            ->format('iibin.tokens.v1')
            ->shuffle(seed: 9001)
            ->prefetch(window: 32)
    )
    ->optimizer(
        Optimizer::adamw()
            ->lr(2e-5)
            ->betas(0.9, 0.95)
            ->weightDecay(0.01)
            ->gradientClipping(1.0)
    )
    ->loss(Loss::crossEntropy()->ignoreIndex(-100))
    ->parallelism(
        Training\Parallelism::hybrid()
            ->dataParallel(3)
            ->tensorParallel(1)
            ->expertParallel(3)
            ->pipelineParallel(1)
            ->collectives('nccl')
            ->rendezvous('king://runs/agent-finetune-run-2026-05-05/rdzv')
    )
    ->schedule(
        Training\Schedule::steps(20_000)
            ->microBatchSize(1)
            ->globalBatchTokens(384_000)
            ->warmupSteps(200)
            ->cosineDecay()
    )
    ->checkpointing(
        Training\Checkpointing::objectStore()
            ->target('king://agents-prod/checkpoints/finetune')
            ->everySteps(250)
            ->async()
            ->resumeAutomatically()
    )
    ->placement(
        Placement::distributed()
            ->nodePool('gpu-small-fleet')
            ->requireGpuMemory('>=80GB')
            ->spreadAcrossRacks()
            ->coLocateHotExperts()
    )
    ->failurePolicy(
        FailurePolicy::training()
            ->elasticRanks(min: 6, max: 12)
            ->reconstructMissingExpertFromCheckpoint()
            ->quarantineBadWorkers()
            ->resumeFromLastConsistentCheckpoint()
    )
    ->start();

$network = $run->coordinateAcross([
    Training\Workers::gpuServer('gpu-a')
        ->host('gpu-a.internal')
        ->websocket('wss://gpu-a.internal/king/training')
        ->rack('rack-a')
        ->gpus(2)
        ->rankSlots(4)
        ->gpuMemoryGb(80)
        ->backend('king-nccl-worker'),
    Training\Workers::gpuServer('gpu-b')
        ->host('gpu-b.internal')
        ->websocket('wss://gpu-b.internal/king/training')
        ->rack('rack-b')
        ->gpus(2)
        ->rankSlots(4)
        ->gpuMemoryGb(80)
        ->backend('king-nccl-worker'),
    Training\Workers::gpuServer('gpu-c')
        ->host('gpu-c.internal')
        ->websocket('wss://gpu-c.internal/king/training')
        ->rack('rack-c')
        ->gpus(2)
        ->rankSlots(4)
        ->gpuMemoryGb(80)
        ->backend('king-nccl-worker'),
]);

$summary = $network->summary();
$leases = $network->rankLeases();
$steps = $network->orchestratorSteps();
$manifest = $network->checkpointManifest();

assert_same('king.training.network.v1', $summary['contract'], 'network contract mismatch');
assert_same('agent-finetune-run-2026-05-05', $summary['run_id'], 'run id mismatch');
assert_same('king_coordinated_gpu_fleet', $summary['topology']['mode'], 'topology mode mismatch');
assert_same(false, $summary['topology']['centralized_compute'], 'centralized compute flag mismatch');
assert_same('control_plane_only', $summary['topology']['controller_role'], 'controller role mismatch');
assert_same(3, $summary['topology']['worker_count'], 'worker count mismatch');
assert_same(6, $summary['topology']['total_gpus'], 'GPU count mismatch');
assert_same(12, $summary['topology']['total_rank_slots'], 'rank slot mismatch');
assert_same(3, $summary['topology']['rack_count'], 'rack count mismatch');
assert_same('accepted', $summary['admission']['state'], 'admission state mismatch');
assert_same(6, $summary['admission']['min_ranks'], 'min ranks mismatch');
assert_same(12, $summary['admission']['max_ranks'], 'max ranks mismatch');
assert_same(12, $summary['admission']['admitted_ranks'], 'admitted ranks mismatch');
assert_same('balanced_round_robin', $summary['admission']['rank_assignment_strategy'], 'rank assignment strategy mismatch');
assert_same(80, $summary['admission']['required_gpu_memory_gb'], 'required GPU memory mismatch');
assert_same([], $summary['admission']['blockers'], 'admission blockers mismatch');
assert_true($summary['admission']['requires_all_workers_nccl'], 'NCCL admission requirement missing');
assert_true($summary['admission']['requires_at_least_two_workers'], 'distributed admission requirement missing');
assert_true($summary['admission']['requires_object_store'], 'object-store admission requirement missing');
assert_true($summary['admission']['requires_iibin'], 'IIBIN admission requirement missing');
assert_true($summary['admission']['requires_websocket'], 'websocket admission requirement missing');

assert_same('king_object_store', $summary['rendezvous']['store'], 'rendezvous store mismatch');
assert_same('if_none_match=*', $summary['rendezvous']['claim_precondition'], 'rendezvous claim precondition mismatch');
assert_same('if_match+expected_version', $summary['rendezvous']['heartbeat_update_precondition'], 'heartbeat precondition mismatch');
assert_same(12, count($leases), 'rank lease count mismatch');
assert_same(0, $leases[0]['rank'], 'first rank mismatch');
assert_same('gpu-a', $leases[0]['worker_id'], 'first rank worker mismatch');
assert_same('moe-rank-claim-v1!agent-finetune-run-2026-05-05!rank-0', $leases[0]['object_id'], 'first rank claim object mismatch');
assert_same('moe-heartbeat-v1!agent-finetune-run-2026-05-05!gpu-a', $leases[0]['heartbeat_object_id'], 'first heartbeat object mismatch');
assert_same('moe-rdzv-v1!agent-finetune-run-2026-05-05', $leases[0]['rendezvous_state_object_id'], 'rendezvous state object mismatch');
assert_same('lease_until_ms', $leases[0]['lease_until_ms_field'], 'lease field mismatch');
assert_same('fencing_token', $leases[0]['fencing_token_field'], 'fencing field mismatch');
assert_same(4, $leases[4]['rank'], 'fifth rank mismatch');
assert_same('gpu-b', $leases[4]['worker_id'], 'fifth rank worker mismatch');
assert_same(8, $leases[8]['rank'], 'ninth rank mismatch');
assert_same('gpu-c', $leases[8]['worker_id'], 'ninth rank worker mismatch');

assert_same('king_pipeline_orchestrator', $summary['orchestrator']['runtime'], 'orchestrator runtime mismatch');
assert_same('king.training.distributed.agent_worker', $summary['orchestrator']['tool'], 'orchestrator tool mismatch');
assert_same('king_pipeline_orchestrator_dispatch', $summary['orchestrator']['dispatch_api'], 'orchestrator dispatch API mismatch');
assert_same('king_pipeline_orchestrator_worker_run_next', $summary['orchestrator']['worker_api'], 'orchestrator worker API mismatch');
assert_same(3, count($steps), 'orchestrator step count mismatch');
assert_same('gpu-a', $steps[0]['worker_id'], 'first step worker mismatch');
assert_same([0, 3, 6, 9], $steps[0]['rank_ids'], 'first step ranks mismatch');
assert_same('gpu-b', $steps[1]['worker_id'], 'second step worker mismatch');
assert_same([1, 4, 7, 10], $steps[1]['rank_ids'], 'second step ranks mismatch');
assert_same('gpu-c', $steps[2]['worker_id'], 'third step worker mismatch');
assert_same([2, 5, 8, 11], $steps[2]['rank_ids'], 'third step ranks mismatch');

assert_same('websocket', $summary['events']['transport'], 'event transport mismatch');
assert_same('iibin', $summary['events']['frame_codec'], 'event frame codec mismatch');
assert_same('KingTrainingRunEventV1', $summary['events']['schema'], 'event schema mismatch');
assert_same('KingTrainingRunEventBatchV1', $summary['events']['batch_schema'], 'event batch schema mismatch');
assert_same(65536, $summary['events']['batch_limit'], 'event batch limit mismatch');
assert_same(1024, $summary['events']['operational_event_batch_limit'], 'operational event batch limit mismatch');
assert_same(1048576, $summary['events']['max_payload_bytes'], 'event payload cap mismatch');
assert_same('king.training.event-frame.v1', $summary['events']['binary_frame']['contract'], 'event frame contract mismatch');
assert_same('KTRN', $summary['events']['binary_frame']['magic'], 'event frame magic mismatch');
assert_same('big_endian', $summary['events']['binary_frame']['byte_order'], 'event frame byte order mismatch');
assert_same('per_worker_stream', $summary['events']['binary_frame']['sequence_scope'], 'event sequence scope mismatch');
assert_true($summary['events']['binary_frame']['reserved_bytes_must_be_zero'], 'reserved byte rule missing');
assert_same('wss://gpu-b.internal/king/training', $summary['events']['worker_endpoints']['gpu-b'], 'worker endpoint mismatch');

assert_same('king_object_store', $manifest['store'], 'checkpoint store mismatch');
assert_same('moe-checkpoint-manifest-v1!agent-finetune-run-2026-05-05', $manifest['manifest_object_id'], 'checkpoint manifest object mismatch');
assert_same('application/vnd.king.training-checkpoint-manifest+json', $manifest['content_type'], 'checkpoint manifest content type mismatch');
assert_same('if_match+expected_version', $manifest['commit_precondition'], 'checkpoint manifest precondition mismatch');
assert_same(12, count($manifest['shards']), 'checkpoint shard count mismatch');
assert_same('moe-checkpoint-shard-v1!agent-finetune-run-2026-05-05!rank-8!latest', $manifest['shards'][8]['object_id'], 'checkpoint shard object mismatch');
assert_same('gpu-c', $manifest['shards'][8]['worker_id'], 'checkpoint shard owner mismatch');
assert_same('application/vnd.king.training-checkpoint-shard+iibin', $manifest['shards'][8]['content_type'], 'checkpoint shard content type mismatch');

$insufficient = $run->coordinateAcross([
    Training\Workers::gpuServer('gpu-alone')
        ->host('gpu-alone.internal')
        ->websocket('wss://gpu-alone.internal/king/training')
        ->rack('rack-a')
        ->gpus(1)
        ->rankSlots(4)
        ->gpuMemoryGb(80)
        ->backend('king-nccl-worker'),
])->summary();
assert_same('insufficient_capacity', $insufficient['admission']['state'], 'single-server admission must fail distributed requirement');
assert_true(in_array('at_least_two_workers_required', $insufficient['admission']['blockers'], true), 'single-server blocker missing');

$weakGpu = $run->coordinateAcross([
    Training\Workers::gpuServer('gpu-a')
        ->host('gpu-a.internal')
        ->websocket('wss://gpu-a.internal/king/training')
        ->rack('rack-a')
        ->gpus(2)
        ->rankSlots(4)
        ->gpuMemoryGb(80)
        ->backend('king-nccl-worker'),
    Training\Workers::gpuServer('gpu-weak')
        ->host('gpu-weak.internal')
        ->websocket('wss://gpu-weak.internal/king/training')
        ->rack('rack-b')
        ->gpus(2)
        ->rankSlots(4)
        ->gpuMemoryGb(40)
        ->backend('king-nccl-worker'),
])->summary();
assert_same('insufficient_capacity', $weakGpu['admission']['state'], 'weak GPU admission must fail memory requirement');
assert_true(in_array('worker_gpu-weak_insufficient_gpu_memory', $weakGpu['admission']['blockers'], true), 'weak GPU blocker missing');

$missingNccl = $run->coordinateAcross([
    Training\Workers::gpuServer('gpu-a')
        ->host('gpu-a.internal')
        ->websocket('wss://gpu-a.internal/king/training')
        ->rack('rack-a')
        ->gpus(3)
        ->rankSlots(6)
        ->gpuMemoryGb(80)
        ->backend('king-nccl-worker'),
    Training\Workers::gpuServer('gpu-no-nccl')
        ->host('gpu-no-nccl.internal')
        ->websocket('wss://gpu-no-nccl.internal/king/training')
        ->rack('rack-b')
        ->gpus(3)
        ->rankSlots(6)
        ->gpuMemoryGb(80)
        ->backend('king-local-worker')
        ->withoutNccl(),
])->summary();
assert_same('insufficient_capacity', $missingNccl['admission']['state'], 'missing NCCL admission must fail');
assert_true(in_array('worker_gpu-no-nccl_missing_nccl', $missingNccl['admission']['blockers'], true), 'missing NCCL blocker absent');

echo "moe distributed network contract ok\n";
