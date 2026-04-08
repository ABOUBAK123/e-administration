import { Controller, Get } from '@nestjs/common';
import { ApiOperation, ApiTags } from '@nestjs/swagger';
import { AdministrationService } from './administration.service';

@ApiTags('theme-public')
@Controller('theme')
export class AdministrationPublicController {
  constructor(private readonly administrationService: AdministrationService) {}

  @Get('global')
  @ApiOperation({ summary: 'Get global theme settings (no authentication required)' })
  async getGlobalTheme() {
    const settings = await this.administrationService.getAppSettings([
      'theme_menu_color',
      'theme_login_background_image',
    ]);
    const map = new Map(settings.map((s) => [s.key, s.value]));
    return {
      menuColor: map.get('theme_menu_color') ?? null,
      loginBackgroundImage: map.get('theme_login_background_image') ?? null,
    };
  }
}
