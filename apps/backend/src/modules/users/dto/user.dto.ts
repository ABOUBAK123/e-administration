import { IsString, IsEmail, IsUUID, MinLength, IsOptional } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class CreateUserDto {
  @ApiProperty({ example: 'john.doe' })
  @IsString()
  username: string;

  @ApiProperty({ example: 'john@example.com' })
  @IsEmail()
  email: string;

  @ApiProperty({ example: 'Password123!' })
  @IsString()
  @MinLength(8)
  password: string;

  @ApiProperty({ example: 'John Doe' })
  @IsString()
  fullName: string;

  @ApiProperty({ example: 'user' })
  @IsOptional()
  @IsString()
  role: string;

  @ApiProperty({ example: 'inactive', required: false })
  @IsOptional()
  @IsString()
  status?: string;

  @ApiProperty({ example: '5 Go', required: false })
  @IsOptional()
  @IsString()
  quota?: string;

  @ApiProperty({ example: 'uuid-de-l-administration', required: false })
  @IsOptional()
  @IsUUID()
  administrationId?: string;

  @ApiProperty({ example: 'Direction des Marches Publics (Destinataire - Trésor Public)', required: false })
  @IsOptional()
  @IsString()
  directionLabel?: string;

  @ApiProperty({ example: 'recipient', required: false })
  @IsOptional()
  @IsString()
  directionScopeType?: 'emitter' | 'recipient';

  @ApiProperty({ example: 'f0f4f7ac-0f70-4af3-93c6-7f4db6d2b11e', required: false })
  @IsOptional()
  @IsString()
  directionScopeId?: string;

  @ApiProperty({ example: 'DMP', required: false })
  @IsOptional()
  @IsString()
  subEntityCode?: string;
}

export class LoginDto {
  @ApiProperty({ example: 'john@example.com' })
  @IsString()
  email: string;

  @ApiProperty({ example: 'Password123!' })
  @IsString()
  password: string;
}

export class UpdateUserDto {
  @ApiProperty({ example: 'john.doe', required: false })
  @IsOptional()
  @IsString()
  username?: string;

  @ApiProperty({ example: 'john@example.com', required: false })
  @IsOptional()
  @IsEmail()
  email?: string;

  @ApiProperty({ example: 'Password123!', required: false })
  @IsOptional()
  @IsString()
  @MinLength(8)
  password?: string;

  @ApiProperty({ example: 'John Doe', required: false })
  @IsOptional()
  @IsString()
  fullName?: string;

  @ApiProperty({ example: 'user', required: false })
  @IsOptional()
  @IsString()
  role?: string;

  @ApiProperty({ example: '5 Go', required: false })
  @IsOptional()
  @IsString()
  quota?: string;

  @ApiProperty({ example: 'uuid-de-l-administration', required: false })
  @IsOptional()
  @IsUUID()
  administrationId?: string;

  @ApiProperty({ example: 'Direction des Marches Publics (Destinataire - Trésor Public)', required: false })
  @IsOptional()
  @IsString()
  directionLabel?: string;

  @ApiProperty({ example: 'recipient', required: false })
  @IsOptional()
  @IsString()
  directionScopeType?: 'emitter' | 'recipient';

  @ApiProperty({ example: 'f0f4f7ac-0f70-4af3-93c6-7f4db6d2b11e', required: false })
  @IsOptional()
  @IsString()
  directionScopeId?: string;

  @ApiProperty({ example: 'DMP', required: false })
  @IsOptional()
  @IsString()
  subEntityCode?: string;
}

export class UpdateCurrentUserDto {
  @ApiProperty({ example: 'john@example.com', required: false })
  @IsOptional()
  @IsEmail()
  email?: string;

  @ApiProperty({ example: 'John Doe', required: false })
  @IsOptional()
  @IsString()
  fullName?: string;

  @ApiProperty({ example: 'CurrentPassword123!', required: false })
  @IsOptional()
  @IsString()
  currentPassword?: string;

  @ApiProperty({ example: 'NewPassword123!', required: false })
  @IsOptional()
  @IsString()
  @MinLength(8)
  password?: string;
}

export class UserResponseDto {
  @ApiProperty()
  id: string;

  @ApiProperty()
  username: string;

  @ApiProperty()
  email: string;

  @ApiProperty()
  fullName: string;

  @ApiProperty()
  avatar: string;

  @ApiProperty()
  role: string;

  @ApiProperty()
  status: string;

  @ApiProperty()
  createdAt: Date;

  @ApiProperty()
  updatedAt: Date;
}
