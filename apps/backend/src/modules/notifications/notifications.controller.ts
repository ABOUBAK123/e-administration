import { Body, Controller, Get, Param, Post, Put, Request, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { IsBoolean, IsInt, IsOptional, IsString, Min, Max } from 'class-validator';
import { AuthGuard } from '@nestjs/passport';
import { NotificationsService } from './notifications.service';

class TestEmailDto {
  @IsString()
  host: string;

  @IsInt()
  @Min(1)
  @Max(65535)
  port: number;

  @IsString()
  user: string;

  @IsString()
  password: string;

  @IsOptional()
  @IsString()
  from?: string;

  @IsOptional()
  @IsBoolean()
  secure?: boolean;
}

@ApiTags('notifications')
@Controller('notifications')
export class NotificationsController {
  constructor(private readonly notificationsService: NotificationsService) {}

  @Post('test-email')
  @UseGuards(AuthGuard('jwt'))
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Send a test SMTP email to the configured account' })
  async testEmail(@Body() body: TestEmailDto) {
    const result = await this.notificationsService.testEmailNotification(body);
    if (!result.sent) {
      return {
        sent: false,
        message: 'SMTP test failed',
        reason: result.reason,
        detail: result.detail,
      };
    }
    return { sent: true, message: 'Test email sent successfully' };
  }

  @Get()
  @UseGuards(AuthGuard('jwt'))
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Get all notifications for the current user' })
  async getNotifications(@Request() req) {
    return this.notificationsService.getNotificationsForUser(req.user.id);
  }

  @Get('unread-count')
  @UseGuards(AuthGuard('jwt'))
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Get unread notification count' })
  async getUnreadCount(@Request() req) {
    const count = await this.notificationsService.getUnreadCount(req.user.id);
    return { count };
  }

  @Put(':id/read')
  @UseGuards(AuthGuard('jwt'))
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Mark a notification as read' })
  async markAsRead(@Param('id') id: string, @Request() req) {
    await this.notificationsService.markAsRead(id, req.user.id);
    return { message: 'Notification marked as read' };
  }

  @Put('read-all')
  @UseGuards(AuthGuard('jwt'))
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Mark all notifications as read' })
  async markAllAsRead(@Request() req) {
    await this.notificationsService.markAllAsRead(req.user.id);
    return { message: 'All notifications marked as read' };
  }
}
