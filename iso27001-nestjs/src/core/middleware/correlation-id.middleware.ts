import { Injectable, NestMiddleware } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';
import { v4 as uuidv4 } from 'uuid';
import { AsyncLocalStorage } from 'async_hooks';

/**
 * A.12: AsyncLocalStorage-backed correlation ID store.
 * Allows any code in the request lifecycle to read the current request ID
 * without threading it through function signatures.
 */
export const correlationIdStorage = new AsyncLocalStorage<string>();

export function getCorrelationId(): string {
  return correlationIdStorage.getStore() ?? 'unknown';
}

/**
 * A.12: Assigns a correlation ID (UUID) to every incoming request.
 * Reuses the X-Request-ID header value if provided by the client,
 * otherwise generates a new UUID.
 * Injects the ID into response headers and AsyncLocalStorage for log enrichment.
 */
@Injectable()
export class CorrelationIdMiddleware implements NestMiddleware {
  use(req: Request, res: Response, next: NextFunction): void {
    const requestId = (req.headers['x-request-id'] as string | undefined) ?? uuidv4();

    // Attach to request for downstream access
    (req as Request & { requestId: string }).requestId = requestId;

    // Propagate to response
    res.setHeader('X-Request-ID', requestId);

    // Run the rest of the request pipeline inside the ALS context
    correlationIdStorage.run(requestId, () => next());
  }
}
