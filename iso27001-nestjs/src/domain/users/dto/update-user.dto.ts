import { IsEmail, IsString, MaxLength, IsOptional } from 'class-validator';
import { Transform } from 'class-transformer';

export class UpdateUserDto {
  @IsOptional()
  @IsEmail({}, { message: 'email must be a valid email address' })
  @Transform(({ value }: { value: string }) => value?.toLowerCase().trim())
  @MaxLength(254)
  email?: string;

  @IsOptional()
  @IsString()
  @MaxLength(200)
  fullName?: string;
}
