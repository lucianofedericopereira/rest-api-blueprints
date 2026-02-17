import {
  Controller,
  Get,
  Post,
  Patch,
  Delete,
  Body,
  Param,
  Query,
  HttpCode,
  HttpStatus,
  NotFoundException,
  ForbiddenException,
  UseGuards,
} from '@nestjs/common';
import { plainToInstance } from 'class-transformer';
import { IsInt, Min, Max } from 'class-validator';
import { Type } from 'class-transformer';
import { UserService } from '../../domain/users/user.service';
import { CreateUserDto } from '../../domain/users/dto/create-user.dto';
import { UpdateUserDto } from '../../domain/users/dto/update-user.dto';
import { UserResponseDto } from '../../domain/users/dto/user-response.dto';
import { JwtAuthGuard } from '../../core/guards/jwt-auth.guard';
import { RolesGuard } from '../../core/guards/roles.guard';
import { Roles } from '../../core/decorators/roles.decorator';
import { Public } from '../../core/decorators/public.decorator';

class PaginationQuery {
  @IsInt() @Min(0) @Type(() => Number)
  skip: number = 0;

  @IsInt() @Min(1) @Max(100) @Type(() => Number)
  limit: number = 20;
}

interface AuthenticatedRequest {
  user?: { id: string; role: string };
}

/**
 * A.9: CRUD user endpoints with RBAC enforcement.
 * POST   /api/v1/users         — public (registration)
 * GET    /api/v1/users         — admin only
 * GET    /api/v1/users/me      — authenticated
 * GET    /api/v1/users/:id     — owner or admin
 * PATCH  /api/v1/users/:id     — owner or admin
 * DELETE /api/v1/users/:id     — admin only
 */
@Controller('api/v1/users')
@UseGuards(JwtAuthGuard, RolesGuard)
export class UsersController {
  constructor(private readonly userService: UserService) {}

  @Public()
  @Post()
  @HttpCode(HttpStatus.CREATED)
  async create(@Body() dto: CreateUserDto): Promise<UserResponseDto> {
    const user = await this.userService.create(dto);
    return plainToInstance(UserResponseDto, user, { excludeExtraneousValues: true });
  }

  @Get()
  @Roles('admin')
  async findAll(@Query() query: PaginationQuery): Promise<UserResponseDto[]> {
    const users = await this.userService.findAll(query.skip, query.limit);
    return users.map((u) => plainToInstance(UserResponseDto, u, { excludeExtraneousValues: true }));
  }

  @Get('me')
  async getMe(req: AuthenticatedRequest): Promise<UserResponseDto> {
    if (!req.user) throw new ForbiddenException({ code: 'FORBIDDEN', message: 'Not authenticated' });
    const user = await this.userService.findById(req.user.id);
    if (!user) throw new NotFoundException({ code: 'NOT_FOUND', message: 'User not found' });
    return plainToInstance(UserResponseDto, user, { excludeExtraneousValues: true });
  }

  @Get(':id')
  async findOne(
    @Param('id') id: string,
    req: AuthenticatedRequest,
  ): Promise<UserResponseDto> {
    const user = await this.userService.findById(id);
    if (!user) throw new NotFoundException({ code: 'NOT_FOUND', message: 'User not found' });
    if (req.user?.role !== 'admin' && req.user?.id !== user.id) {
      throw new ForbiddenException({ code: 'FORBIDDEN', message: 'Access denied' });
    }
    return plainToInstance(UserResponseDto, user, { excludeExtraneousValues: true });
  }

  @Patch(':id')
  async update(
    @Param('id') id: string,
    @Body() dto: UpdateUserDto,
    req: AuthenticatedRequest,
  ): Promise<UserResponseDto> {
    const user = await this.userService.findById(id);
    if (!user) throw new NotFoundException({ code: 'NOT_FOUND', message: 'User not found' });
    if (req.user?.role !== 'admin' && req.user?.id !== user.id) {
      throw new ForbiddenException({ code: 'FORBIDDEN', message: 'Access denied' });
    }
    const updated = await this.userService.update(user, dto);
    return plainToInstance(UserResponseDto, updated, { excludeExtraneousValues: true });
  }

  @Delete(':id')
  @Roles('admin')
  @HttpCode(HttpStatus.NO_CONTENT)
  async remove(@Param('id') id: string): Promise<void> {
    const user = await this.userService.findById(id);
    if (!user) throw new NotFoundException({ code: 'NOT_FOUND', message: 'User not found' });
    await this.userService.delete(id);
  }
}
