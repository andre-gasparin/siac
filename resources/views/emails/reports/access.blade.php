<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f6f8;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:580px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#164a6c;background:linear-gradient(135deg,#164a6c 0%,#246d91 100%);padding:26px 32px;">
                            <p style="margin:0 0 6px 0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#bae6fd;">
                                {{ $appName }}
                            </p>
                            <h1 style="margin:0;font-size:20px;line-height:1.3;font-weight:600;color:#ffffff;">
                                Relatório de Controle Químico
                            </h1>
                            <p style="margin:6px 0 0 0;font-size:13px;color:#e0f2fe;">
                                {{ $teamName }}
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:28px 32px 24px 32px;">
                            <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#0f172a;">
                                Olá, <strong>{{ $recipientName }}</strong>!
                            </p>

                            @if ($interactions !== [])
                                <p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:#475569;">
                                    Há novas interações registradas no relatório de <strong>{{ $teamName }}</strong> referente a <strong>{{ $dateReference }}</strong>.
                                </p>
                            @else
                                <p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:#475569;">
                                    O relatório de controle químico de <strong>{{ $teamName }}</strong> referente a <strong>{{ $dateReference }}</strong> já está disponível para consulta.
                                </p>
                            @endif

                            <!-- Summary Card -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:22px;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding:3px 0;font-size:12px;color:#64748b;width:42%;">Unidade / Cliente</td>
                                                <td style="padding:3px 0;font-size:13px;font-weight:600;color:#0f172a;">{{ $teamName }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:3px 0;font-size:12px;color:#64748b;">Data de referência</td>
                                                <td style="padding:3px 0;font-size:13px;font-weight:600;color:#0f172a;">{{ $dateReference }}</td>
                                            </tr>
                                            @if ($issuerName)
                                                <tr>
                                                    <td style="padding:3px 0;font-size:12px;color:#64748b;">Emitido por</td>
                                                    <td style="padding:3px 0;font-size:13px;font-weight:600;color:#0f172a;">{{ $issuerName }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            @if ($interactions !== [])
                                <!-- Interactions Box -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;margin-bottom:24px;">
                                    <tr>
                                        <td style="padding:14px 18px;">
                                            <p style="margin:0 0 10px 0;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#0369a1;">
                                                Novas interações registradas
                                            </p>
                                            @foreach ($interactions as $interaction)
                                                <div style="margin-bottom:{{ $loop->last ? '0' : '10px' }};padding-bottom:{{ $loop->last ? '0' : '10px' }};border-bottom:{{ $loop->last ? 'none' : '1px solid #e0f2fe' }};">
                                                    <p style="margin:0 0 3px 0;font-size:12px;font-weight:600;color:#0f172a;">
                                                        {{ $interaction['system'] }}
                                                        <span style="font-weight:400;color:#64748b;">· {{ $interaction['author'] }}</span>
                                                    </p>
                                                    <p style="margin:0;font-size:13px;line-height:1.5;color:#334155;">
                                                        {{ $interaction['body'] }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:6px 0 22px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;background-color:#164a6c;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:12px 28px;border-radius:8px;">
                                            Acessar relatório
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px 0;font-size:12px;line-height:1.5;color:#64748b;">
                                Este link é individual e identifica seu acesso e seus comentários no relatório. Não compartilhe este endereço com terceiros.
                            </p>

                            <p style="margin:0;font-size:11px;line-height:1.5;color:#94a3b8;word-break:break-all;">
                                Se o botão acima não funcionar, copie e cole este endereço no navegador:<br>
                                <a href="{{ $actionUrl }}" style="color:#164a6c;text-decoration:underline;">{{ $actionUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 32px;text-align:center;">
                            <p style="margin:0;font-size:11px;color:#94a3b8;">
                                Enviado automaticamente por {{ $appName }} · {{ $teamName }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
