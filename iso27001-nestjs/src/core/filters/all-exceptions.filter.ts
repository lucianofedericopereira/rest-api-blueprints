import {
  ExceptionFilter,
  Catch,
  ArgumentsHost,
  HttpException,
  HttpStatus,
  Logger,
} from '@nestjs/common';
import { Request, Response } from 'express';
import { getCorrelationId } from '../middleware/correlation-id.middleware';

/**
 * A.14: Global exception filter — no stack traces or internal details exposed to clients.
 * All error responses use the fixed shape { code, message, request_id }.
 */
@Catch()
export class AllExceptionsFilter implements ExceptionFilter {
  private readonly logger = new Logger('ExceptionFilter');

  catch(exception: unknown, host: ArgumentsHost): void {
    const ctx = host.switchToHttp();
    const response = ctx.getResponse<Response>();
    const request = ctx.getRequest<Request>();

    let statusCode = HttpStatus.INTERNAL_SERVER_ERROR;
    let code = 'INTERNAL_SERVER_ERROR';
    let message = 'An unexpected error occurred';

    if (exception instanceof HttpException) {
      statusCode = exception.getStatus();
      const body = exception.getResponse();
      if (typeof body === 'object' && body !== null && 'code' in body) {
        code = (body as { code: string }).code;
        message = ((body as unknown) as { message?: string }).message ?? message;
      } else if (typeof body === 'string') {
        message = body;
        code = this.statusToCode(statusCode);
      } else {
        message = (body as { message?: string }).message ?? message;
        code = this.statusToCode(statusCode);
      }
    } else {
      // A.14: Log full details internally, expose nothing to client
      this.logger.error(
        JSON.stringify({
          message: 'unhandled_exception',
          error: exception instanceof Error ? exception.message : String(exception),
          stack: exception instanceof Error ? exception.stack : undefined,
          path: request.path,
          request_id: getCorrelationId(),
        }),
      );
    }

    response.status(statusCode).json({
      code,
      message,
      request_id: getCorrelationId(),
    });
  }

  private statusToCode(status: number): string {
    const map: Record<number, string> = {
      400: 'BAD_REQUEST',
      401: 'UNAUTHORIZED',
      403: 'FORBIDDEN',
      404: 'NOT_FOUND',
      409: 'CONFLICT',
      422: 'UNPROCESSABLE_ENTITY',
      429: 'TOO_MANY_REQUESTS',
      500: 'INTERNAL_SERVER_ERROR',
      503: 'SERVICE_UNAVAILABLE',
    };
    return map[status] ?? 'ERROR';
  }
}
