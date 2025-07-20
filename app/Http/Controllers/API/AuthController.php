<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Traits\EncTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Http\Controllers\Controller as Controller;

use function Pest\Laravel\json;

class AuthController extends Controller
{
    use EncTrait;
    /**
     * Exibe uma lista dos recursos.
     */
    public function index()
    {
        //
    }


    /**
     * Armazena um novo recurso no armazenamento.
     */
    public function register(Request $request)
    {
        $input = $request->all(); // data

        // Remover caracteres não numéricos antes de validar
        $input['cpf_cnpj'] = preg_replace('/\D/', '', $input['cpf_cnpj'] ?? '');
        $input['telefone'] = preg_replace('/\D/', '', $input['telefone'] ?? '');

        $input['name'] = strtoupper($input['name'] ?? '');
        $input['email'] = strtolower($input['email'] ?? '');
        $input['tipo_pessoa'] = strtolower($input['tipo_pessoa'] ?? '');

        $atributos =  [
            'name' => 'Nome Completo',
            'email' => 'E-mail',
            'password' => 'Senha',
            'cpf_cnpj' => $input['tipo_pessoa'] === 'pj' ? 'CNPJ' : 'CPF',
            'tipo_pessoa' => 'Tipo de Pessoa',
            'telefone' => 'Telefone',
            'inscricao_estadual' => 'Inscrição Estadual',
        ];
        $input['tipo_pessoa'] = strtolower($input['tipo_pessoa'] ?? 'pf');

        $validated = Validator::make(
            $input,
            [
            'name' => 'required|min:8|string|max:100',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => ['required' , 'string' , 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'tipo_pessoa' => ['required', 'in:pf,pj'],
                    'cpf_cnpj' => [
                        'required',
                        'string',
                        'max:20',
                        'unique:users,cpf_cnpj',
                        function ($attribute, $value, $fail) use ($input) {
                            $value = preg_replace('/\D/', '', $value);
                            if (($input['tipo_pessoa'] ?? '') === 'pf' && !$this->validarCPF($value)) {
                                $fail('O CPF informado é inválido.');
                            }
                            if (($input['tipo_pessoa'] ?? '') === 'pj' && !$this->validarCNPJ($value)) {
                                $fail('O CNPJ informado é inválido.');
                            }
                        },
                ],
            'telefone' => ['required', 'string', 'regex:/^\(?\d{2}\)?[\s-]?\d{4,5}-?\d{4}$/'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/', 'min:9'],
        ],
            [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto válido.',
            'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'min' => 'O campo :attribute deve ter no mínimo :min caracteres.',

            'name.min' => 'O :attribute informado não é válido.',

            'email.email' => 'O :attribute informado não é válido.',
            'email.unique' => 'Este :attribute já existe cadastrado.',

            'confirmed' => 'A confirmação da :attribute não confere.',
            'password.letters' => 'A :attribute deve conter pelo menos uma letra.',
            'password.mixed' => 'A :attribute deve conter letras maiúsculas e minúsculas.',
            'password.numbers' => 'A :attribute deve conter pelo menos um número.',
            'password.symbols' => 'A :attribute deve conter pelo menos um símbolo especial.',

            'cpf_cnpj.unique' => 'Este :attribute já existe cadastrado.',
            'tipo_pessoa.in' => 'O :attribute deve ser PF ou PJ.',
            'telefone.regex' => 'O :attribute informado não é válido.',
            'inscricao_estadual.regex' => 'O campo :attribute deve conter apenas números.',
        ],
            $atributos
        );

        if ($validated->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validated->errors()
            ], 422);
        }
        $validated = $validated->validated(); // validação

        try {
            // criar usuario
            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tipo_pessoa' => $validated['tipo_pessoa'],
                'cpf_cnpj' => $validated['cpf_cnpj'],
                'telefone' => $validated['telefone'],
                'inscricao_estadual' => $validated['inscricao_estadual'] ?? null,
            ]);

            // forçar login após cadastro
            $loginRequest = new Request([
                'login' => $validated['email'],
                'password' => $input['password'],
            ]);

            return $this->login($loginRequest);

        } catch (\Exception $e) {
            return response()->json(['ok' => false,'message' => 'Erro - Não foi possivel cadastrar.',
            ], 500);
        }

    }
    public function login(Request $request)
    {

        /*    $recapchaTokenVerificado = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => env('RECAPTCHA_SECRET_KEY'),
                'response' => $request->recaptcha_token,
            ]);
            $recapchaTokenVerificado = $recapchaTokenVerificado->json();
        // preciso implementar o captcha ainda */

        $input = $request->all();
        $validator = Validator::make($input, [
            'login' => [
                'required','string',function ($attribute, $value, $fail) {
                    $emailValido = filter_var($value, FILTER_VALIDATE_EMAIL);
                    $cpfValido = strlen(preg_replace('/\D/', '', $value)) === 11;
                    $cnpjValido = strlen(preg_replace('/\D/', '', $value)) === 14;

                    if (!$emailValido && !$cpfValido && !$cnpjValido) {
                        $fail('Informe um e-mail, CPF ou CNPJ válido.');
                    }
                }
            ],
            'password' => ['required','string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto válido.',
            'password.min' => 'A :attribute deve ter no mínimo :min caracteres.',
            'password.letters' => 'A :attribute deve conter pelo menos uma letra.',
            'password.mixed' => 'A :attribute deve conter pelo menos uma letra maiúscula e minúscula.',
            'password.numbers' => 'A :attribute deve conter pelo menos um número.',
            'password.symbols' => 'A :attribute deve conter pelo menos um símbolo especial.',
        ], [
            'login' => 'E-mail, CPF ou CNPJ',
            'password' => 'Senha',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $login = $input['login'];
            $password = $input['password'];
            $apenasNumeros = preg_replace('/\D/', '', $login);

            $credentials = ['password' => $password];
            if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $credentials['email'] = $login;
            } elseif (strlen($apenasNumeros) === 11 || strlen($apenasNumeros) === 14) {
                $credentials['cpf_cnpj'] = $apenasNumeros;
            }

            /*  if (!($recapchaTokenVerificado['success'] ?? false)) { // implementar ainda
                  response()->json(['ok' => false, 'message' => 'Falha na verificação reCAPTCHA.'], 422);
              }
            */


            if (Auth::attempt($credentials)) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                //dd($user);

                //, remove todos os tokens devido ao novo login
                $user->tokens()->delete();

                $accessToken = $user->createToken('access-token', ['post:read'], now()->addMinutes(15))->plainTextToken;
                $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(7))->plainTextToken;

                return response()->json([
                    'ok' => true,
                    'access_token' => $this->encriptado($accessToken),
                    'expires_in' => 15 * 60, // 15minutos, expira token
                ])->cookie(
                    'refresh_token',
                    $this->encriptado($refreshToken),
                    7 * 24 * 60, // 7 dias, pensando em colocar 1 dia.
                    '/', // path
                    null, // domínio
                    true, // Secure , em https... - necessario devido a questão de envio de cookies
                    true,  // HttpOnly
                    false, // raw
                    'None'  // same site // necessario para dominios diferentes
                );
            }

            return response()->json(['ok' => false, 'message' => 'E-mail ou senha inválidos'], 401);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false,'message' => 'Erro - Não foi possivel realizar login.',], 500);
        }
    }

    public function refreshToken(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        try {
            $decryptedToken = $this->desencriptado($refreshToken);

            // Valida o refresh token
            $token = PersonalAccessToken::findToken($decryptedToken);

            if (!$token || !$token->can('refresh')) {
                return response()->json(['ok' => false, 'message' => 'Refresh token inválido'], 401);
            }

            $user = $token->tokenable;
            $user->tokens()->where('name', 'access-token')->delete();
            // Gera novo access token
            $newAccessToken = $user->createToken('access-token', ['post:read'], now()->addMinutes(15))->plainTextToken;

            return response()->json([
                'ok' => true,
               'access_token' => $this->encriptado($newAccessToken),
                'expires_in' => 15 * 60
            ]);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Refresh token inválido'], 401);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $refreshToken = $request->cookie('refresh_token');
            $decryptedToken = $this->desencriptado($refreshToken);

            if (!$refreshToken && !$user) {
                return response()->json(['ok' => false, 'message' => 'Nenhuma sessão ativa encontrada'], 401);
            }

            // Se usuário autenticado, remove todos os tokens dele
            if ($user) {
                $user->tokens()->delete();
            }

            // Se tiver refresh token, valida e remove
            if ($refreshToken) {
                try {

                    $token = PersonalAccessToken::findToken($decryptedToken);

                    if ($token) {
                        // Remove o token específico
                        $token->delete();

                        // e limpa tokens inativos
                        if (!$user && $token->tokenable) {
                            $token->tokenable->tokens()->delete();
                        }
                    }
                } catch (\Exception $e) {
                    return response()->json(['ok' => false, 'message' => 'Não encontrado'], 401);
                }
            }

            // remove o cookie
            return response()->json([
                'ok' => true,
                'message' => 'Logout realizado com sucesso'
            ], 200)->cookie(
                'refresh_token',
                '',
                -1,
                null,
                null,
                true,
                true
            );

        } catch (\Throwable $e) {
            // mesmo com erro remove o cookie para logout
            return response()->json([
                'ok' => false,
                'message' => 'Erro ao realizar logout, sessão foi encerrada'
            ], 500)->cookie(
                'refresh_token',
                '',
                -1,
                null,
                null,
                true,
                true
            );
        }
    }
    /**
     * Exibe o recurso especificado.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Atualiza o recurso especificado no armazenamento.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove o recurso especificado do armazenamento.
     */
    public function destroy(string $id)
    {
        //
    }

    // FUNCOES PARA O AUTH
    public function validarCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        $multiplicadores1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $multiplicadores2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $soma1 = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma1 += $cnpj[$i] * $multiplicadores1[$i];
        }

        $resto1 = $soma1 % 11;
        $digito1 = $resto1 < 2 ? 0 : 11 - $resto1;

        if ((int)$cnpj[12] !== $digito1) {
            return false;
        }

        $soma2 = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma2 += $cnpj[$i] * $multiplicadores2[$i];
        }

        $resto2 = $soma2 % 11;
        $digito2 = $resto2 < 2 ? 0 : 11 - $resto2;

        return (int)$cnpj[13] === $digito2;
    }

    public function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;

            if (!isset($cpf[$t]) || (int)$cpf[$t] !== $d) {
                return false;
            }
        }

        return true;
    }


}
