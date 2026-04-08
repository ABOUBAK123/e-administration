import {
  Controller,
  Get,
  Post,
  Put,
  Delete,
  Param,
  Body,
  Request,
  UseGuards,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { AuthGuard } from '@nestjs/passport';
import { WorkflowsService } from './workflows.service';
import { CreateWorkflowDto, CreateWorkflowTemplateDto, UpdateWorkflowDto } from './dto/workflow.dto';

@ApiTags('workflows')
@ApiBearerAuth()
@UseGuards(AuthGuard('jwt'))
@Controller('workflows')
export class WorkflowsController {
  constructor(private readonly workflowsService: WorkflowsService) {}

  @Get()
  @ApiOperation({ summary: 'Get workflows visible to current user' })
  findAll(@Request() req) {
    return this.workflowsService.findAll(req.user.id);
  }

  @Get('templates')
  @ApiOperation({ summary: 'Get workflow templates for current administration' })
  findTemplates(@Request() req) {
    return this.workflowsService.findTemplates(req.user.id);
  }

  @Post('templates')
  @ApiOperation({ summary: 'Create workflow template for current administration' })
  createTemplate(@Body() createTemplateDto: CreateWorkflowTemplateDto, @Request() req) {
    return this.workflowsService.createTemplate(req.user.id, createTemplateDto);
  }

  @Get(':id')
  @ApiOperation({ summary: 'Get workflow by ID' })
  findOne(@Param('id') id: string) {
    return this.workflowsService.findOne(id);
  }

  @Get(':id/steps')
  @ApiOperation({ summary: 'Get workflow steps' })
  getSteps(@Param('id') id: string) {
    return this.workflowsService.getSteps(id);
  }

  @Get(':id/executions/:executionId')
  @ApiOperation({ summary: 'Get workflow execution' })
  getExecution(@Param('id') id: string, @Param('executionId') executionId: string) {
    return this.workflowsService.getExecution(executionId);
  }

  @Post()
  @ApiOperation({ summary: 'Create new workflow' })
  create(@Body() createWorkflowDto: CreateWorkflowDto, @Request() req) {
    return this.workflowsService.create(req.user.id, createWorkflowDto);
  }

  @Post(':id/execute')
  @ApiOperation({ summary: 'Execute workflow' })
  executeWorkflow(
    @Param('id') id: string,
    @Body() body: { documentId: string },
    @Request() req,
  ) {
    return this.workflowsService.executeWorkflow(body.documentId, id, req.user.id);
  }

  @Post('execution/:executionId/advance')
  @ApiOperation({ summary: 'Advance workflow step' })
  advanceWorkflowStep(
    @Param('executionId') executionId: string,
    @Body() body: { stepIndex: number; decision?: string },
    @Request() req,
  ) {
    return this.workflowsService.advanceWorkflowStep(executionId, {
      stepIndex: body.stepIndex,
      decision: body.decision,
    });
  }

  @Post('execution/:executionId/reject')
  @ApiOperation({ summary: 'Reject workflow' })
  rejectWorkflow(
    @Param('executionId') executionId: string,
    @Body() body: { reason?: string },
    @Request() req,
  ) {
    return this.workflowsService.rejectWorkflow(executionId, body.reason);
  }

  @Put(':id')
  @ApiOperation({ summary: 'Update workflow' })
  update(@Param('id') id: string, @Body() updateWorkflowDto: UpdateWorkflowDto, @Request() req) {
    return this.workflowsService.update(id, updateWorkflowDto);
  }

  @Delete(':id')
  @ApiOperation({ summary: 'Delete workflow' })
  delete(@Param('id') id: string, @Request() req) {
    return this.workflowsService.delete(id);
  }
}
