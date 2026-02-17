package audit

import "sync"

// EventBus is a simple synchronous in-process event bus.
// Matches the FastAPI EventBus and NestJS EventEmitter2 patterns.
type EventBus struct {
	mu        sync.RWMutex
	listeners []func(event any)
}

// Subscribe registers a listener function.
func (b *EventBus) Subscribe(fn func(any)) {
	b.mu.Lock()
	defer b.mu.Unlock()
	b.listeners = append(b.listeners, fn)
}

// Publish dispatches an event to all registered listeners synchronously.
func (b *EventBus) Publish(event any) {
	b.mu.RLock()
	defer b.mu.RUnlock()
	for _, fn := range b.listeners {
		fn(event)
	}
}
