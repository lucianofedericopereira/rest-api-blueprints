defmodule Iso27001Phoenix.Guardian do
  @moduledoc """
  A.9: JWT authentication via Guardian.

  Access tokens:  30 min TTL (HS256)
  Refresh tokens: 7 day TTL  (HS256)
  Each token gets a unique `jti` claim (Guardian default).
  """
  use Guardian, otp_app: :iso27001_phoenix

  alias Iso27001Phoenix.Domain.Users.User
  alias Iso27001Phoenix.Repo

  @impl true
  def subject_for_token(%User{id: id}, _claims), do: {:ok, to_string(id)}
  def subject_for_token(_, _), do: {:error, :unknown_resource}

  @impl true
  def resource_from_claims(%{"sub" => id}) do
    case Repo.get(User, id) do
      nil  -> {:error, :not_found}
      user -> {:ok, user}
    end
  end

  def resource_from_claims(_), do: {:error, :missing_sub}

  @doc """
  Issues an access + refresh token pair for the given user.
  """
  def issue_token_pair(%User{} = user) do
    cfg = Application.get_env(:iso27001_phoenix, __MODULE__, [])
    refresh_ttl = Keyword.get(cfg, :refresh_ttl, {7, :days})

    with {:ok, access_token,  _} <- encode_and_sign(user, %{"typ" => "access"},  token_type: "access"),
         {:ok, refresh_token, _} <- encode_and_sign(user, %{"typ" => "refresh"}, token_type: "refresh", ttl: refresh_ttl) do
      {:ok, %{access_token: access_token, refresh_token: refresh_token, token_type: "bearer"}}
    end
  end

  @doc """
  Verifies a refresh token and returns the user.
  """
  def verify_refresh(token) do
    with {:ok, claims}   <- decode_and_verify(token, %{"typ" => "refresh"}),
         {:ok, user}     <- resource_from_claims(claims) do
      {:ok, user}
    end
  end
end
