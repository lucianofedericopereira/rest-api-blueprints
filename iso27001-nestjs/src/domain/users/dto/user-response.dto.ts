import { Exclude, Expose } from 'class-transformer';
import { UserRole } from '../user.entity';

/** A.14: Never expose hashed_password or deleted_at to API clients */
@Exclude()
export class UserResponseDto {
  @Expose() id!: string;
  @Expose() email!: string;
  @Expose() fullName!: string | null;
  @Expose() role!: UserRole;
  @Expose() isActive!: boolean;
  @Expose() createdAt!: Date;
  @Expose() updatedAt!: Date;
}
