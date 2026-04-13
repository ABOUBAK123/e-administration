import {
  Controller,
  Post,
  Get,
  Delete,
  Param,
  Body,
  Request,
  Res,
  UseGuards,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { AuthGuard } from '@nestjs/passport';
import { Response } from 'express';
import { QrcodeService } from './qrcode.service';
import { GenerateQrCodeDto, VerifyQrCodeDto } from './dto/qrcode.dto';

@ApiTags('qrcode')
@ApiBearerAuth()
@Controller('qrcode')
export class QrcodeController {
  constructor(private readonly qrcodeService: QrcodeService) {}

  @Post('generate')
  @UseGuards(AuthGuard('jwt'))
  @ApiOperation({ summary: 'Generate QR code for document' })
  generate(@Body() generateQrCodeDto: GenerateQrCodeDto, @Request() req) {
    return this.qrcodeService.generate(
      generateQrCodeDto.documentId,
      req.user.id,
      generateQrCodeDto
    );
  }

  @Get('document/:documentId')
  @UseGuards(AuthGuard('jwt'))
  @ApiOperation({ summary: 'Get QR codes for document' })
  getQrCodesForDocument(@Param('documentId') documentId: string) {
    return this.qrcodeService.getQrCodesForDocument(documentId);
  }

  @Post('verify')
  @UseGuards(AuthGuard('jwt'))
  @ApiOperation({ summary: 'Verify QR code' })
  verify(@Body() verifyQrCodeDto: VerifyQrCodeDto) {
    const verificationCode = this.qrcodeService.extractVerificationCode(verifyQrCodeDto.qrcodeData);
    return this.qrcodeService.verify(verificationCode);
  }

  @Get('scan/:verificationCode')
  @ApiOperation({ summary: 'Public QR scan endpoint that redirects to digital document version' })
  async scanAndRedirect(@Param('verificationCode') verificationCode: string, @Res() res: Response) {
    const result =
      await this.qrcodeService.resolveDigitalVersionByVerificationCode(verificationCode);
    return res.redirect(302, result.verificationPageUrl || result.digitalVersionUrl);
  }

  @Delete(':qrcodeId')
  @UseGuards(AuthGuard('jwt'))
  @ApiOperation({ summary: 'Revoke QR code' })
  revoke(@Param('qrcodeId') qrcodeId: string, @Request() _req) {
    return this.qrcodeService.revoke(qrcodeId);
  }

  @Post('cleanup')
  @UseGuards(AuthGuard('jwt'))
  @ApiOperation({ summary: 'Clean up expired QR codes' })
  cleanupExpiredQrcodes() {
    return this.qrcodeService.cleanupExpiredQrcodes();
  }

  @Get('public/verify/:documentNumber')
  @ApiOperation({ summary: 'Public: verify document authenticity by document number' })
  verifyByDocumentNumber(@Param('documentNumber') documentNumber: string) {
    return this.qrcodeService.verifyByDocumentNumber(documentNumber);
  }
}
