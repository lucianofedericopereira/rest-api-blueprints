import {
  Controller,
  Post,
  Body,
  HttpCode,
  HttpStatus,
  UnauthorizedException,
  Inject,
} from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { IsEmail, IsString } from 'class-validator';
import { v4 as uuidv4 } from 'uuid';
import { Public } from '../../core/decorators/public.decorator';
import { USER_REPOSITORY } from '../../domain/users/user-repository.interface';
import { IUserRepository } from '../../domain/users/user-repository.interface';
import { UserService } from '../../domain/users/user.service';
import { BruteForceGuard } from '../../infrastructure/security/brute-force.guard';
import { loadConfig } from '../../core/config/configuration';
import { JwtPayload } from '../../core/auth/jwt.strategy';

/** A.14: Input validation DTOs */
class LoginDto {
  @IsEmail()
  email!: string;

  @IsString()
  password!: string;
}

class RefreshDto {
  @IsString()
  refresh_token!: string;
}

interface TokenPair {
  access_token: string;
  refresh_token: string;
  token_type: string;
}

/**
 * A.9: Authentication endpoints.
 * POST /api/v1/auth/login   — issues access + refresh token pair
 * POST /api/v1/auth/refresh — rotates token pair
 * POST /api/v1/auth/logout  — client-side only (stateless JWT)
 */
@Controller('api/v1/auth')
export class AuthController {
  constructor(
    @Inject(USER_REPOSITORY) private readonly userRepo: IUserRepository,
    private readonly userService: UserService,
    private readonly jwtService: JwtService,
    private readonly bruteForce: BruteForceGuard,
  ) {}

  @Public()
  @Post('login')
  @HttpCode(HttpStatus.OK)
  async login(@Body() dto: LoginDto): Promise<TokenPair> {
    // A.9: Brute-force check before any DB query
    await this.bruteForce.check(dto.email);

    const user = await this.userRepo.findByEmail(dto.email);
    if (!user || !(await this.userService.verifyPassword(dto.password, user.hashedPassword))) {
      await this.bruteForce.recordFailure(dto.email);
      throw new UnauthorizedException({ code: 'UNAUTHORIZED', message: 'Invalid credentials' });
    }

    if (!user.isActive) {
      throw new UnauthorizedException({ code: 'UNAUTHORIZED', message: 'User inactive' });
    }

    await this.bruteForce.clear(dto.email);
    return this.issueTokenPair(user.id, user.role);
  }

  @Post('refresh')
  @HttpCode(HttpStatus.OK)
  async refresh(@Body() dto: RefreshDto): Promise<TokenPair> {
    let payload: JwtPayload;
    try {
      payload = this.jwtService.verify<JwtPayload>(dto.refresh_token);
    } catch {
      throw new UnauthorizedException({ code: 'UNAUTHORIZED', message: 'Invalid or expired refresh token' });
    }

    if (payload.type !== 'refresh') {
      throw new UnauthorizedException({ code: 'UNAUTHORIZED', message: 'Invalid token type' });
    }

    const user = await this.userRepo.findById(payload.sub);
    if (!user || !user.isActive) {
      throw new UnauthorizedException({ code: 'UNAUTHORIZED', message: 'User not found or inactive' });
    }

    return this.issueTokenPair(user.id, user.role);
  }

  @Post('logout')
  @HttpCode(HttpStatus.OK)
  logout(): { message: string } {
    // A.9: Stateless JWT — client discards tokens.
    // For full revocation, add a Redis token denylist here.
    return { message: 'Logged out successfully' };
  }

  private issueTokenPair(userId: string, role: string): TokenPair {
    const config = loadConfig();

    const accessPayload: JwtPayload = {
      sub: userId,
      role,
      jti: uuidv4(),
      type: 'access',
    };
    const refreshPayload: JwtPayload = {
      sub: userId,
      role,
      jti: uuidv4(),
      type: 'refresh',
    };

    return {
      access_token: this.jwtService.sign(accessPayload, {
        expiresIn: config.jwtAccessExpiresIn,
      }),
      refresh_token: this.jwtService.sign(refreshPayload, {
        expiresIn: config.jwtRefreshExpiresIn,
      }),
      token_type: 'Bearer',
    };
  }
}
