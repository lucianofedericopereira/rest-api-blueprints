import { Injectable, UnauthorizedException } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { ExtractJwt, Strategy } from 'passport-jwt';
import { loadConfig } from '../config/configuration';

export interface JwtPayload {
  sub: string;
  role: string;
  jti: string;
  type: 'access' | 'refresh';
}

/**
 * A.9: Passport JWT strategy.
 * Validates the Bearer token from the Authorization header.
 */
@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
  constructor() {
    const config = loadConfig();
    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      ignoreExpiration: false,
      secretOrKey: config.jwtSecret,
    });
  }

  validate(payload: JwtPayload): { id: string; role: string } {
    if (payload.type !== 'access') {
      throw new UnauthorizedException({ code: 'UNAUTHORIZED', message: 'Invalid token type' });
    }
    return { id: payload.sub, role: payload.role };
  }
}
