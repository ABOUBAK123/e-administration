import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { Notification } from './notification.entity';

@Injectable()
export class NotificationsService {
  private readonly logger = new Logger(NotificationsService.name);
  private readonly transporter: any | null;
  private readonly fromAddress: string;

  constructor(
    private readonly configService: ConfigService,
    @InjectRepository(Notification)
    private notificationRepository: Repository<Notification>,
  ) {
    const host = this.configService.get<string>('mail.host');
    const port = this.configService.get<number>('mail.port');
    const secureConfig = this.configService.get<boolean>('mail.secure');
    const user = this.configService.get<string>('mail.user');
    const password = this.configService.get<string>('mail.password');

    this.fromAddress = this.configService.get<string>('mail.from') || 'noreply@e-parapheur.local';

    if (!host || !port || !user || !password) {
      this.logger.warn('Mail configuration is incomplete; email notifications are disabled.');
      this.transporter = null;
      return;
    }

    let nodemailerLib: any;
    try {
      nodemailerLib = require('nodemailer');
    } catch {
      this.logger.warn('nodemailer package not found; email notifications are disabled.');
      this.transporter = null;
      return;
    }

    this.transporter = nodemailerLib.createTransport({
      host,
      port,
      secure: secureConfig ?? Number(port) === 465,
      auth: {
        user,
        pass: password,
      },
    });
  }

  async sendEmailNotification(params: {
    to: string;
    subject: string;
    text: string;
    html?: string;
  }): Promise<{ sent: boolean; reason?: string }> {
    if (!this.transporter) {
      this.logger.warn(`Email skipped (mailer disabled) to=${params.to}, subject=${params.subject}`);
      return { sent: false, reason: 'mailer_disabled' };
    }

    try {
      await this.transporter.sendMail({
        from: this.fromAddress,
        to: params.to,
        subject: params.subject,
        text: params.text,
        html: params.html,
      });
      return { sent: true };
    } catch (error) {
      this.logger.error(`Failed to send email to ${params.to}: ${(error as Error).message}`);
      return { sent: false, reason: 'send_failed' };
    }
  }

  async testEmailNotification(params: {
    host: string;
    port: number;
    user: string;
    password: string;
    from?: string;
    secure?: boolean;
  }): Promise<{ sent: boolean; reason?: string; detail?: string }> {
    let nodemailerLib: any;
    try {
      nodemailerLib = require('nodemailer');
    } catch {
      this.logger.warn('nodemailer package not found; test email is disabled.');
      return { sent: false, reason: 'mailer_disabled' };
    }

    try {
      const transporter = nodemailerLib.createTransport({
        host: params.host,
        port: params.port,
        secure: params.secure ?? Number(params.port) === 465,
        auth: {
          user: params.user,
          pass: params.password,
        },
      });

      await transporter.sendMail({
        from: params.from || params.user,
        to: params.user,
        subject: 'Test SMTP E-Parapheur',
        text: 'Email de test SMTP depuis E-Parapheur.',
      });

      return { sent: true };
    } catch (error) {
      const detail = (error as Error).message;
      this.logger.error(`SMTP test failed for ${params.user}: ${detail}`);
      return { sent: false, reason: 'send_failed', detail };
    }
  }

  // ── In-app notification CRUD ──

  async createNotification(data: {
    recipientId: string;
    title: string;
    message: string;
    type?: 'info' | 'validation' | 'signature' | 'workflow' | 'system';
    workflowId?: string;
    executionId?: string;
    actionUrl?: string;
  }): Promise<Notification> {
    const notification = this.notificationRepository.create({
      recipientId: data.recipientId,
      title: data.title,
      message: data.message,
      type: data.type || 'info',
      workflowId: data.workflowId || null,
      executionId: data.executionId || null,
      actionUrl: data.actionUrl || null,
    });
    return this.notificationRepository.save(notification);
  }

  async getNotificationsForUser(recipientId: string): Promise<Notification[]> {
    return this.notificationRepository.find({
      where: { recipientId },
      order: { createdAt: 'DESC' },
      take: 50,
    });
  }

  async getUnreadCount(recipientId: string): Promise<number> {
    return this.notificationRepository.count({
      where: { recipientId, isRead: false },
    });
  }

  async markAsRead(notificationId: string, recipientId: string): Promise<void> {
    await this.notificationRepository.update(
      { id: notificationId, recipientId },
      { isRead: true },
    );
  }

  async markAllAsRead(recipientId: string): Promise<void> {
    await this.notificationRepository.update(
      { recipientId, isRead: false },
      { isRead: true },
    );
  }
}
