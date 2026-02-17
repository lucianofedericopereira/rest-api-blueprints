import { User } from './user.entity';

/** Repository interface — domain knows only the contract, not the ORM. */
export interface IUserRepository {
  findById(id: string): Promise<User | null>;
  findByEmail(email: string): Promise<User | null>;
  existsByEmail(email: string): Promise<boolean>;
  findAll(skip: number, limit: number): Promise<User[]>;
  save(user: User): Promise<User>;
  softDelete(id: string): Promise<void>;
}

export const USER_REPOSITORY = 'USER_REPOSITORY';
