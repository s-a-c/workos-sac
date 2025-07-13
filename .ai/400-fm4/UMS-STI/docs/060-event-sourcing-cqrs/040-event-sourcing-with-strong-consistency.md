# Event Sourcing with Strong Consistency
Created: 29/06/2025, 13:05:42
Last Updated: 29/06/2025, 13:12:12
---

### User

Discuss, describe and explain event sourcing whilst retaining strong state consistency, that is avoiding the “eventual consistency “ paradigm often associated with CQRS.

Please articulate and challenge any assumptions and/or misconceptions implicit in my framing of this task


---

### Assistant (claude-4-sonnet)

I'll address your question about event sourcing with strong consistency, but first let me challenge some key assumptions in your framing.

## Challenging Your Assumptions

**Misconception 1: Event Sourcing = Eventual Consistency**
Event sourcing itself doesn't inherently require eventual consistency. This is a common conflation that occurs because:
- Event sourcing is often discussed alongside CQRS
- CQRS implementations frequently use eventual consistency for performance
- Distributed event sourcing systems may choose eventual consistency

**Misconception 2: CQRS Always Implies Eventual Consistency**
CQRS (Command Query Responsibility Segregation) is an architectural pattern that separates read and write models. The consistency model is an implementation choice, not a requirement of CQRS itself.

## Event Sourcing with Strong Consistency

### Core Concept
Event sourcing stores state changes as a sequence of events rather than current state. Strong consistency means all reads reflect the most recent committed writes immediately.

### Implementation Approaches

**1. Single-Writer, Synchronous Projections**
```typescript
class EventStore {
  private events: Event[] = [];
  private projections: Map<string, any> = new Map();

  async appendEvent(event: Event): Promise<void> {
    // Atomic operation: append event and update projections
    await this.transaction(async (tx) => {
      await tx.appendEvent(event);
      await this.updateProjections(event, tx);
    });
  }

  private async updateProjections(event: Event, tx: Transaction): Promise<void> {
    // Synchronously update all projections within the same transaction
    for (const projection of this.projectionHandlers) {
      await projection.handle(event, tx);
    }
  }
}
```

**2. Event Store with Immediate Read Models**
```sql
-- Single transaction ensures consistency
BEGIN TRANSACTION;
  INSERT INTO events (stream_id, event_type, event_data, version) 
  VALUES (@streamId, @eventType, @eventData, @version);
  
  UPDATE read_model_accounts 
  SET balance = balance + @amount 
  WHERE account_id = @accountId;
COMMIT;
```

**3. Aggregate-Based Strong Consistency**
```typescript
class Account {
  private events: Event[] = [];
  private version: number = 0;

  static async load(id: string, eventStore: EventStore): Promise<Account> {
    const events = await eventStore.getEvents(id);
    const account = new Account();
    account.replay(events);
    return account;
  }

  debit(amount: number): void {
    if (this.balance < amount) {
      throw new Error("Insufficient funds");
    }
    this.addEvent(new MoneyDebited(amount));
  }

  async save(eventStore: EventStore): Promise<void> {
    // Optimistic concurrency control ensures consistency
    await eventStore.saveEvents(this.id, this.events, this.version);
  }
}
```

### Consistency Guarantees

**Strong Consistency Patterns:**

1. **Transactional Event Store**: Events and projections updated atomically
2. **Optimistic Concurrency Control**: Version-based conflict detection
3. **Single-Writer Principle**: One writer per aggregate/stream
4. **Synchronous Projections**: Read models updated before command completion

### Trade-offs and Considerations

**Benefits of Strong Consistency:**
- Immediate consistency for business-critical operations
- Simplified reasoning about system state
- No need for complex conflict resolution
- Better user experience for real-time operations

**Costs:**
- Reduced throughput and increased latency
- Limited scalability compared to eventual consistency
- Higher resource utilization
- Potential for increased contention

### When to Choose Strong Consistency

**Appropriate Scenarios:**
- Financial transactions
- Inventory management
- Regulatory compliance requirements
- Systems where stale reads are unacceptable

**Implementation Strategies:**
```typescript
// Example: Strongly consistent order processing
class OrderService {
  async processOrder(command: CreateOrderCommand): Promise<void> {
    await this.eventStore.transaction(async (tx) => {
      // Check inventory (strongly consistent read)
      const inventory = await this.inventoryProjection.get(
        command.productId, 
        tx
      );
      
      if (inventory.quantity < command.quantity) {
        throw new Error("Insufficient inventory");
      }

      // Create events
      const events = [
        new OrderCreated(command),
        new InventoryReserved(command.productId, command.quantity)
      ];

      // Atomic: persist events and update projections
      await tx.appendEvents(events);
      await this.updateProjections(events, tx);
    });
  }
}
```

## Conclusion

Event sourcing with strong consistency is not only possible but often preferable for certain domains. The key is choosing the right consistency model based on your specific requirements rather than defaulting to eventual consistency because it's commonly associated with event sourcing architectures.

The main architectural decision is whether to prioritize consistency or availability/partition tolerance (CAP theorem), not whether event sourcing can support strong consistency.


---

### User

Discuss, describe and explain CAP theorem.
Use Laravel 12 and PHP 8.4 for examples.


---

### Assistant (claude-4-sonnet)

# CAP Theorem: Consistency, Availability, and Partition Tolerance

## Core Definition

The CAP theorem, formulated by Eric Brewer in 2000, states that in any distributed data system, you can guarantee at most **two** of the following three properties simultaneously:

- **Consistency (C)**: All nodes see the same data simultaneously
- **Availability (A)**: System remains operational and responsive
- **Partition Tolerance (P)**: System continues operating despite network failures

## Understanding Each Component

### Consistency
Every read receives the most recent write or an error. All nodes have identical data at any given time.

### Availability  
Every request receives a response (success or failure) without guarantee of most recent data.

### Partition Tolerance
System continues functioning when network partitions occur (nodes can't communicate).

## CAP Combinations in Practice

Since network partitions are inevitable in distributed systems, the real choice is between **CP** (Consistency + Partition Tolerance) or **AP** (Availability + Partition Tolerance).

## Laravel 12 + PHP 8.4 Examples

### CP System: Strongly Consistent Database Cluster

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Exceptions\ConsistencyException;

class StronglyConsistentOrderService
{
    /**
     * CP System: Prioritizes consistency over availability
     * Will fail if partition prevents reaching quorum
     */
    public function createOrder(array $orderData): Order
    {
        return DB::transaction(function () use ($orderData) {
            // Read from primary only (strong consistency)
            $inventory = DB::connection('primary')
                ->table('inventory')
                ->where('product_id', $orderData['product_id'])
                ->lockForUpdate() // Prevents concurrent modifications
                ->first();

            if (!$inventory || $inventory->quantity < $orderData['quantity']) {
                throw new ConsistencyException('Insufficient inventory');
            }

            // All operations must succeed or none do
            $order = Order::create($orderData);
            
            DB::connection('primary')
                ->table('inventory')
                ->where('product_id', $orderData['product_id'])
                ->decrement('quantity', $orderData['quantity']);

            // Synchronously replicate to all nodes or fail
            $this->replicateToAllNodes($order);

            return $order;
        });
    }

    private function replicateToAllNodes(Order $order): void
    {
        $replicas = ['replica1', 'replica2', 'replica3'];
        
        foreach ($replicas as $replica) {
            try {
                DB::connection($replica)->table('orders')->insert($order->toArray());
            } catch (\Exception $e) {
                // If any replica fails, the entire operation fails
                throw new ConsistencyException("Replication failed to {$replica}");
            }
        }
    }
}
```

### AP System: Eventually Consistent with High Availability

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SyncOrderToReplicas;

class HighlyAvailableOrderService
{
    /**
     * AP System: Prioritizes availability over strict consistency
     * Accepts orders even if some nodes are unreachable
     */
    public function createOrder(array $orderData): Order
    {
        // Use local cache for inventory check (may be stale)
        $cachedInventory = Cache::get("inventory.{$orderData['product_id']}");
        
        if ($cachedInventory && $cachedInventory < $orderData['quantity']) {
            // Soft check - proceed anyway as inventory might be outdated
            logger()->warning('Potential inventory shortage', $orderData);
        }

        // Create order on local node immediately
        $order = Order::create($orderData);

        // Asynchronously sync to other nodes (eventual consistency)
        Queue::dispatch(new SyncOrderToReplicas($order));

        // Update local inventory optimistically
        $this->updateLocalInventory($orderData['product_id'], $orderData['quantity']);

        return $order;
    }

    private function updateLocalInventory(int $productId, int $quantity): void
    {
        try {
            DB::table('inventory')
                ->where('product_id', $productId)
                ->decrement('quantity', $quantity);
                
            // Update cache
            Cache::decrement("inventory.{$productId}", $quantity);
        } catch (\Exception $e) {
            // Log but don't fail the order
            logger()->error('Local inventory update failed', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### Partition Detection and Handling

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PartitionAwareService
{
    private array $nodes = [
        'node1' => 'https://node1.example.com',
        'node2' => 'https://node2.example.com',
        'node3' => 'https://node3.example.com',
    ];

    public function handleRequest(array $data): array
    {
        $availableNodes = $this->detectAvailableNodes();
        $partitionDetected = count($availableNodes) < count($this->nodes);

        if ($partitionDetected) {
            return $this->handlePartitionScenario($data, $availableNodes);
        }

        return $this->handleNormalOperation($data);
    }

    private function detectAvailableNodes(): array
    {
        $available = [];
        
        foreach ($this->nodes as $name => $url) {
            try {
                $response = Http::timeout(2)->get("{$url}/health");
                if ($response->successful()) {
                    $available[$name] = $url;
                }
            } catch (\Exception $e) {
                logger()->warning("Node {$name} unreachable", ['error' => $e->getMessage()]);
            }
        }

        return $available;
    }

    private function handlePartitionScenario(array $data, array $availableNodes): array
    {
        $majorityThreshold = ceil(count($this->nodes) / 2);
        
        if (count($availableNodes) >= $majorityThreshold) {
            // CP approach: Continue with majority quorum
            return $this->processWithQuorum($data, $availableNodes);
        } else {
            // AP approach: Accept request but mark as potentially inconsistent
            return $this->processWithoutQuorum($data);
        }
    }

    private function processWithQuorum(array $data, array $availableNodes): array
    {
        // Ensure consistency across majority of nodes
        $results = [];
        
        foreach ($availableNodes as $name => $url) {
            try {
                $response = Http::post("{$url}/process", $data);
                $results[$name] = $response->json();
            } catch (\Exception $e) {
                throw new \Exception("Quorum operation failed on {$name}");
            }
        }

        return ['status' => 'consistent', 'results' => $results];
    }

    private function processWithoutQuorum(array $data): array
    {
        // Process locally, queue for later synchronization
        $localResult = $this->processLocally($data);
        
        Queue::dispatch(new SynchronizeWhenPartitionHeals($data, $localResult));

        return [
            'status' => 'accepted_eventually_consistent',
            'result' => $localResult,
            'warning' => 'Data may be inconsistent due to network partition'
        ];
    }
}
```

### Configuration for Different CAP Choices

```php
<?php

// config/database.php
return [
    'connections' => [
        // CP Configuration: Strong consistency
        'cp_primary' => [
            'driver' => 'mysql',
            'host' => env('DB_CP_HOST'),
            'database' => env('DB_CP_DATABASE'),
            'options' => [
                PDO::ATTR_TIMEOUT => 30, // Longer timeout for consistency
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ],
            'read_write_timeout' => 60,
            'sticky' => true, // Always use same connection
        ],

        // AP Configuration: High availability
        'ap_cluster' => [
            'driver' => 'mysql',
            'read' => [
                'host' => [
                    env('DB_AP_READ_HOST_1'),
                    env('DB_AP_READ_HOST_2'),
                    env('DB_AP_READ_HOST_3'),
                ],
            ],
            'write' => [
                'host' => env('DB_AP_WRITE_HOST'),
            ],
            'options' => [
                PDO::ATTR_TIMEOUT => 5, // Quick timeout for availability
            ],
            'read_write_timeout' => 10,
            'sticky' => false, // Allow different connections
        ],
    ],
];
```

### Middleware for CAP Strategy Selection

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CapStrategyMiddleware
{
    public function handle(Request $request, Closure $next, string $strategy = 'ap'): mixed
    {
        // Set CAP strategy based on route requirements
        match ($strategy) {
            'cp' => $this->configureForConsistency($request),
            'ap' => $this->configureForAvailability($request),
            default => throw new \InvalidArgumentException("Unknown CAP strategy: {$strategy}")
        };

        return $next($request);
    }

    private function configureForConsistency(Request $request): void
    {
        // Use primary database connection
        config(['database.default' => 'cp_primary']);
        
        // Set strict consistency headers
        $request->headers->set('X-Consistency-Level', 'strong');
        $request->headers->set('X-Read-Preference', 'primary');
    }

    private function configureForAvailability(Request $request): void
    {
        // Use clustered connection with read replicas
        config(['database.default' => 'ap_cluster']);
        
        // Allow eventual consistency
        $request->headers->set('X-Consistency-Level', 'eventual');
        $request->headers->set('X-Read-Preference', 'secondary-preferred');
    }
}
```

### Route Configuration

```php
<?php

// routes/web.php
use App\Http\Middleware\CapStrategyMiddleware;

// CP Routes: Financial transactions requiring strong consistency
Route::middleware([CapStrategyMiddleware::class.':cp'])->group(function () {
    Route::post('/payments', [PaymentController::class, 'create']);
    Route::post('/transfers', [TransferController::class, 'execute']);
});

// AP Routes: Social features prioritizing availability
Route::middleware([CapStrategyMiddleware::class.':ap'])->group(function () {
    Route::post('/posts', [PostController::class, 'create']);
    Route::post('/comments', [CommentController::class, 'store']);
    Route::get('/feed', [FeedController::class, 'index']);
});
```

## Key Takeaways

1. **CAP is about trade-offs**: You must choose what to sacrifice during network partitions
2. **Context matters**: Different parts of your application may require different CAP strategies
3. **Partitions are inevitable**: In distributed systems, you're really choosing between CP or AP
4. **Laravel flexibility**: The framework supports both approaches through configuration and middleware
5. **Business requirements drive choice**: Financial systems typically choose CP, social media often chooses AP

The examples demonstrate how Laravel 12 and PHP 8.4 can implement both consistency-focused and availability-focused architectures depending on your specific requirements.


---
