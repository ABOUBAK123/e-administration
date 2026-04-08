import { Controller, Post, Get, Delete, Param, Body, UseGuards, Request } from '@nestjs/common';
import { ApiBearerAuth, ApiTags, ApiOperation } from '@nestjs/swagger';
import { AuthGuard } from '@nestjs/passport';
import { SignaturesService } from './signatures.service';
import { CreateSignatureDto, RequestSignatureDto } from './dto/signature.dto';

@ApiTags('signatures')
@ApiBearerAuth()
@UseGuards(AuthGuard('jwt'))
@Controller('signatures')
export class SignaturesController {
  constructor(private readonly signaturesService: SignaturesService) {}

  @Post(':documentId/sign')
  @ApiOperation({ summary: 'Sign a document' })
  sign(
    @Param('documentId') documentId: string,
    @Body() signatureData: CreateSignatureDto,
    @Request() req,
  ) {
    return this.signaturesService.sign(documentId, req.user.id, signatureData);
  }

  @Get(':documentId')
  @ApiOperation({ summary: 'Get signatures for a document' })
  getSignatures(@Param('documentId') documentId: string) {
    return this.signaturesService.getSignatures(documentId);
  }

  @Post(':documentId/request')
  @ApiOperation({ summary: 'Request signature for a document' })
  requestSignature(
    @Param('documentId') documentId: string,
    @Body() requestData: RequestSignatureDto,
    @Request() req,
  ) {
    return this.signaturesService.requestSignature(documentId, req.user.id, requestData);
  }

  @Post(':documentId/verify/:signatureId')
  @ApiOperation({ summary: 'Verify signature' })
  verify(@Param('documentId') documentId: string, @Param('signatureId') signatureId: string) {
    return this.signaturesService.verify(documentId, signatureId);
  }

  @Delete(':signatureId')
  @ApiOperation({ summary: 'Delete signature' })
  deleteSignature(@Param('signatureId') signatureId: string) {
    return this.signaturesService.deleteSignature(signatureId);
  }

  @Get('pending/:userId')
  @ApiOperation({ summary: 'Get pending signatures for a user' })
  getPendingSignatures(@Param('userId') userId: string) {
    return this.signaturesService.getPendingSignatures(userId);
  }

  @Post('request/:requestId/respond')
  @ApiOperation({ summary: 'Respond to signature request' })
  respondToSignatureRequest(
    @Param('requestId') requestId: string,
    @Body() body: { accepted: boolean },
  ) {
    return this.signaturesService.respondToSignatureRequest(requestId, body.accepted);
  }
}
