import { Inject, Injectable, ConflictException } from '@nestjs/common';
import { EventEmitter2 } from '@nestjs/event-emitter';
import * as crypto from 'crypto';
import * as bcrypt from 'bcrypt';
import { User } from './user.entity';
import { IUserRepository, USER_REPOSITORY } from './user-repository.interface';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';
import { UserCreatedEvent } from './events/user-created.event';

const BCRYPT_ROUNDS = 12; // A.10: bcrypt cost ≥ 12

@Injectable()
export class UserService {
  constructor(
    @Inject(USER_REPOSITORY)
    private readonly repo: IUserRepository,
    private readonly events: EventEmitter2,
  ) {}

  async create(dto: CreateUserDto): Promise<User> {
    if (await this.repo.existsByEmail(dto.email)) {
      throw new ConflictException('Email already exists');
    }

    // A.10: bcrypt password hashing (cost 12)
    const hashedPassword = await bcrypt.hash(dto.password, BCRYPT_ROUNDS);

    const user = new User();
    user.email = dto.email;
    user.hashedPassword = hashedPassword;
    user.fullName = dto.fullName ?? null;
    user.role = 'viewer';
    user.isActive = true;

    const saved = await this.repo.save(user);

    // A.12: Emit domain event for audit trail — never log raw email
    const emailHash = crypto.createHash('sha256').update(saved.email).digest('hex');
    this.events.emit('user.created', new UserCreatedEvent(saved.id, emailHash, saved.role));

    return saved;
  }

  async findAll(skip: number, limit: number): Promise<User[]> {
    return this.repo.findAll(skip, limit);
  }

  async findById(id: string): Promise<User | null> {
    return this.repo.findById(id);
  }

  async findByEmail(email: string): Promise<User | null> {
    return this.repo.findByEmail(email);
  }

  async update(user: User, dto: UpdateUserDto): Promise<User> {
    if (dto.email && dto.email !== user.email) {
      if (await this.repo.existsByEmail(dto.email)) {
        throw new ConflictException('Email already exists');
      }
      user.email = dto.email;
    }
    if (dto.fullName !== undefined) {
      user.fullName = dto.fullName;
    }
    return this.repo.save(user);
  }

  async delete(id: string): Promise<void> {
    return this.repo.softDelete(id);
  }

  /** A.9: Verify password with constant-time bcrypt comparison */
  async verifyPassword(plaintext: string, hash: string): Promise<boolean> {
    return bcrypt.compare(plaintext, hash);
  }
}
