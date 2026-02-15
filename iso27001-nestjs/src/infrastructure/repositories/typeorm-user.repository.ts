import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { User } from '../../domain/users/user.entity';
import { IUserRepository } from '../../domain/users/user-repository.interface';

@Injectable()
export class TypeOrmUserRepository implements IUserRepository {
  constructor(
    @InjectRepository(User)
    private readonly orm: Repository<User>,
  ) {}

  findById(id: string): Promise<User | null> {
    return this.orm.findOne({ where: { id } });
  }

  findByEmail(email: string): Promise<User | null> {
    return this.orm.findOne({ where: { email } });
  }

  async existsByEmail(email: string): Promise<boolean> {
    const count = await this.orm.count({ where: { email } });
    return count > 0;
  }

  findAll(skip: number, limit: number): Promise<User[]> {
    return this.orm.find({ skip, take: limit, order: { createdAt: 'DESC' } });
  }

  save(user: User): Promise<User> {
    return this.orm.save(user);
  }

  async softDelete(id: string): Promise<void> {
    await this.orm.softDelete(id);
  }
}
