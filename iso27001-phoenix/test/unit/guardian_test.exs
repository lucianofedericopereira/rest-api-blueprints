defmodule Iso27001Phoenix.GuardianTest do
  use ExUnit.Case, async: true

  alias Iso27001Phoenix.Guardian
  alias Iso27001Phoenix.Domain.Users.User

  @user %User{id: Uniq.UUID.uuid4(), email: "test@example.com", role: "viewer"}

  test "issue_token_pair returns access and refresh tokens" do
    {:ok, %{access_token: at, refresh_token: rt, token_type: tt}} =
      Guardian.issue_token_pair(@user)

    assert is_binary(at)
    assert is_binary(rt)
    assert tt == "bearer"
  end

  test "access token verifies successfully" do
    {:ok, %{access_token: at}} = Guardian.issue_token_pair(@user)
    assert {:ok, _claims} = Guardian.decode_and_verify(at)
  end

  test "refresh token has typ=refresh claim" do
    {:ok, %{refresh_token: rt}} = Guardian.issue_token_pair(@user)
    {:ok, claims} = Guardian.decode_and_verify(rt)
    assert claims["typ"] == "refresh"
  end

  test "two token pairs produce different JTIs" do
    {:ok, %{access_token: at1}} = Guardian.issue_token_pair(@user)
    {:ok, %{access_token: at2}} = Guardian.issue_token_pair(@user)

    {:ok, c1} = Guardian.decode_and_verify(at1)
    {:ok, c2} = Guardian.decode_and_verify(at2)

    refute c1["jti"] == c2["jti"]
  end
end
