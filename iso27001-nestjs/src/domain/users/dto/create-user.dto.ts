import { IsEmail, IsString, MinLength, MaxLength, IsOptional } from 'class-validator';
import { Transform } from 'class-transformer';

/** A.14: Input validation — class-validator enforced by NestJS ValidationPipe */
export class CreateUserDto {
  @IsEmail({}, { message: 'email must be a valid email address' })
  @Transform(({ value }: { value: string }) => value?.toLowerCase().trim())
  @MaxLength(254)
  email!: string;

  @IsString()
  @MinLength(8, { message: 'password must be at least 8 characters' })
  @MaxLength(128)
  password!: string;

  @IsOptional()
  @IsString()
  @MaxLength(200)
  fullName?: string;
}
